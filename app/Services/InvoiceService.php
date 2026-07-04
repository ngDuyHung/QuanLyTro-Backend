<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ServiceType;
use App\Exceptions\Domain\BusinessException;
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Lấy hóa đơn thuộc đúng chủ trọ đang đăng nhập.
     *
     * Dùng lại logic ownership giống InvoiceController hiện tại:
     * invoice -> lease -> room -> property -> user_id.
     */
    public function findOwnedInvoice(int $invoiceId, int $userId): Invoice
    {
        return Invoice::query()
            ->with(['lease.room.property', 'items'])
            ->whereHas('lease.room.property', function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->findOrFail($invoiceId);
    }

    /**
     * Tạo hóa đơn thủ công hoặc từ nghiệp vụ tạo hóa đơn hàng tháng.
     *
     * Lưu ý:
     * - FE không nên tự gửi total_amount/status.
     * - Backend tự tính tổng tiền từ items.
     * - Hóa đơn mới tạo mặc định là draft.
     */
    public function createInvoice(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId): Invoice {
            $lease = $this->findOwnedLease((int) $data['lease_id'], $userId);

            $invoiceType = $data['invoice_type'] ?? 'monthly';

            $this->assertInvoicePeriodNotDuplicated(
                leaseId: $lease->id,
                periodFrom: $data['period_from'],
                periodTo: $data['period_to'],
                invoiceType: $invoiceType
            );

            $preparedItems = $this->prepareInvoiceItems($data['items'] ?? []);

            if (empty($preparedItems['items'])) {
                throw new BusinessException('Hóa đơn phải có ít nhất một dòng chi tiết.');
            }

            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'property_id' => $lease->room->property_id,
                'room_id' => $lease->room_id,

                'invoice_code' => $this->generateInvoiceCode((int)$lease->room_id),
                'invoice_type' => $invoiceType,

                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],

                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,

                'status' => 'draft',

                'subtotal_amount' => $preparedItems['subtotal_amount'],
                'previous_debt_amount' => $preparedItems['previous_debt_amount'],
                'discount_amount' => $preparedItems['discount_amount'],
                'surcharge_amount' => $preparedItems['surcharge_amount'],
                'total_amount' => $preparedItems['total_amount'],

                'paid_amount' => 0,
                'remaining_amount' => $preparedItems['total_amount'],

                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($preparedItems['items'] as $item) {
                $invoice->items()->create($item);
            }

            // Tìm tất cả các chỉ số điện/nước chưa được chốt của phòng này đến hết ngày kỳ này
            \App\Models\MeterReading::where('lease_id', $lease->id)
                ->whereNull('invoice_id')
                ->where('reading_date', '<=', $data['period_to'])
                ->update(['invoice_id' => $invoice->id]);

            return $invoice->fresh(['lease.room.property', 'items']);
        });
    }

    /**
     * Phát hành hóa đơn.
     *
     * Khi phát hành:
     * - status chuyển từ draft sang issued.
     * - issued_at được set.
     * - locked_at được set để hạn chế sửa dòng tiền trực tiếp.
     */
    public function issueInvoice(Invoice $invoice, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->refresh();

            if ($invoice->status !== 'draft') {
                throw new BusinessException('Chỉ có thể phát hành hóa đơn đang ở trạng thái nháp.');
            }

            if ($invoice->items()->count() === 0) {
                throw new BusinessException('Không thể phát hành hóa đơn chưa có dòng chi tiết.');
            }

            if ((int) $invoice->total_amount <= 0) {
                throw new BusinessException('Không thể phát hành hóa đơn có tổng tiền bằng 0.');
            }

            $now = now();

            $invoice->update([
                'status' => 'issued',
                'issue_date' => $invoice->issue_date ?? $now->toDateString(),
                'issued_at' => $now,
                'locked_at' => $now,
            ]);

            return $invoice->fresh(['lease.room.property', 'items']);
        });
    }

    /**
     * Hủy hóa đơn.
     *
     * Chỉ cho hủy khi hóa đơn chưa có tiền thanh toán/cấn trừ.
     */
    public function cancelInvoice(Invoice $invoice, string $reason, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason): Invoice {
            $invoice->refresh();

            if ($invoice->status === 'cancelled') {
                throw new BusinessException('Hóa đơn đã bị hủy trước đó.');
            }

            if ((int) $invoice->paid_amount > 0) {
                throw new BusinessException('Không thể hủy hóa đơn đã có phát sinh thanh toán.');
            }

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // Trả lại trạng thái tự do (null) cho chỉ số để có thể tạo lại hóa đơn khác
            \App\Models\MeterReading::where('invoice_id', $invoice->id)->update(['invoice_id' => null]);

            return $invoice->fresh(['lease.room.property', 'items']);
        });
    }

    /**
     * Cập nhật lại paid_amount, remaining_amount và status của hóa đơn.
     *
     * Hàm này nên được gọi sau khi:
     * - tạo allocation mới,
     * - hủy giao dịch thu chi,
     * - điều chỉnh cấn trừ.
     */
    public function refreshPaymentStatus(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->refresh();

            if ($invoice->status === 'cancelled') {
                return $invoice;
            }

            $paidAmount = (int) FinancialTransactionAllocation::query()
                ->join(
                    'financial_transactions',
                    'financial_transactions.id',
                    '=',
                    'financial_transaction_allocations.financial_transaction_id'
                )
                ->where('financial_transaction_allocations.invoice_id', $invoice->id)
                ->where('financial_transaction_allocations.allocation_type', 'payment')
                ->where('financial_transactions.status', 'confirmed')
                ->sum('financial_transaction_allocations.allocated_amount');

            $totalAmount = (int) $invoice->total_amount;
            $remainingAmount = max(0, $totalAmount - $paidAmount);

            $status = $this->resolveInvoiceStatus(
                currentStatus: (string) $invoice->status,
                totalAmount: $totalAmount,
                paidAmount: $paidAmount,
                remainingAmount: $remainingAmount,
                dueDate: $invoice->due_date ? (string) $invoice->due_date : null
            );

            $invoice->update([
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $status,
            ]);

            return $invoice->fresh(['lease.room.property', 'items']);
        });
    }

    /**
     * Tìm hợp đồng thuộc chủ trọ đang đăng nhập.
     */
    private function findOwnedLease(int $leaseId, int $userId): Lease
    {
        return Lease::query()
            ->with(['room.property', 'tenant'])
            ->whereHas('room.property', function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->findOrFail($leaseId);
    }

    /**
     * Chặn tạo trùng hóa đơn cùng kỳ cho cùng hợp đồng và cùng loại hóa đơn.
     */
    private function assertInvoicePeriodNotDuplicated(
        int $leaseId,
        string $periodFrom,
        string $periodTo,
        string $invoiceType
    ): void {
        $exists = Invoice::query()
            ->where('lease_id', $leaseId)
            ->where('period_from', $periodFrom)
            ->where('period_to', $periodTo)
            ->where('invoice_type', $invoiceType)
            ->exists();

        if ($exists) {
            throw new BusinessException('Hợp đồng này đã có hóa đơn cùng kỳ và cùng loại.');
        }
    }

    /**
     * Chuẩn bị dòng chi tiết hóa đơn và tính tổng tiền.
     */
    private function prepareInvoiceItems(array $items): array
    {
        $preparedItems = [];

        $subtotalAmount = 0;
        $previousDebtAmount = 0;
        $discountAmount = 0;
        $surchargeAmount = 0;

        foreach ($items as $index => $item) {
            $chargeType = (string) ($item['charge_type'] ?? '');

            if ($chargeType === '') {
                throw new BusinessException('Dòng hóa đơn thiếu loại khoản phí.');
            }

            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPriceSnapshot = (int) ($item['unit_price_snapshot'] ?? 0);
            $freeQuantitySnapshot = (float) ($item['free_quantity_snapshot'] ?? 0);
            $billableQuantity = max(0, $quantity - $freeQuantitySnapshot);

            $amount = array_key_exists('amount', $item)
                ? (int) $item['amount']
                : (int) round($billableQuantity * $unitPriceSnapshot);

            if ($chargeType === 'discount' && $amount > 0) {
                $amount *= -1;
            }

            if ($chargeType === 'previous_debt') {
                $previousDebtAmount += max(0, $amount);
            } elseif ($chargeType === 'discount') {
                $discountAmount += abs($amount);
            } elseif ($chargeType === 'surcharge') {
                $surchargeAmount += max(0, $amount);
            } else {
                $subtotalAmount += $amount;
            }

            $preparedItems[] = [
                'service_price_id' => $item['service_price_id'] ?? null,
                'charge_type' => $chargeType,
                'description' => $item['description'] ?? $this->defaultItemDescription($chargeType),
                'unit' => $item['unit'] ?? null,
                'quantity' => $quantity,
                'unit_price_snapshot' => $unitPriceSnapshot,
                'free_quantity_snapshot' => (float) ($item['free_quantity_snapshot'] ?? 0),
                'amount' => $amount,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ];
        }

        $totalAmount = max(
            0,
            $subtotalAmount + $previousDebtAmount + $surchargeAmount - $discountAmount
        );

        return [
            'items' => $preparedItems,
            'subtotal_amount' => max(0, $subtotalAmount),
            'previous_debt_amount' => $previousDebtAmount,
            'discount_amount' => $discountAmount,
            'surcharge_amount' => $surchargeAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function defaultItemDescription(string $chargeType): string
    {
        return match ($chargeType) {
            'room' => 'Tiền phòng',
            'electricity' => 'Tiền điện',
            'water' => 'Tiền nước',
            'garbage' => 'Tiền rác',
            'internet' => 'Tiền internet',
            'previous_debt' => 'Công nợ cũ',
            'damage_fee' => 'Phí hư hỏng/phát sinh',
            'discount' => 'Giảm trừ',
            'surcharge' => 'Phụ thu',
            default => 'Khoản khác',
        };
    }

    /**
     * Xác định trạng thái hóa đơn sau khi cập nhật paid/remaining.
     */
    private function resolveInvoiceStatus(
        string $currentStatus,
        int $totalAmount,
        int $paidAmount,
        int $remainingAmount,
        ?string $dueDate
    ): string {
        if ($currentStatus === 'draft') {
            return 'draft';
        }

        if ($currentStatus === 'cancelled') {
            return 'cancelled';
        }

        if ($totalAmount <= 0 || $remainingAmount <= 0) {
            return 'paid';
        }

        if ($paidAmount > 0 && $paidAmount < $totalAmount) {
            return 'partially_paid';
        }

        if ($dueDate && now()->toDateString() > $dueDate) {
            return 'overdue';
        }

        return 'issued';
    }

    /**
     * TÍNH TOÁN TRƯỚC DỮ LIỆU HÓA ĐƠN (PREPARE)
     * Trả về mảng dữ liệu gợi ý cho Frontend hiển thị.
     */


    public function prepareInvoiceData(int $leaseId, string $periodTo, int $userId): array
    {
        $lease = Lease::with(['room.property', 'tenant', 'serviceItems', 'members'])
            ->whereHas('room.property', fn($q) => $q->where('user_id', $userId))
            ->findOrFail($leaseId);
        //dd($lease->occupants_count); // dòng nay để debug xem số lượng người ở ghép, dùng cho free_units per_person

        $items = [];
        $propertyId = $lease->room->property_id;

        // 1. Tiền phòng cố định
        $items[] = [
            'charge_type' => 'room',
            'description' => 'Tiền phòng',
            'unit' => 'Tháng',
            'quantity' => 1,
            'unit_price_snapshot' => $lease->room_price, // Thay vì $lease->room->current_price
            'amount' => $lease->room_price,
            'is_utility' => false,
        ];

        // Lấy toàn bộ cấu hình giá dịch vụ của khu trọ
        $applicablePrices = ServicePrice::getApplicablePrices((int)$propertyId);

        // Lấy các chỉ số điện/nước chưa lên hóa đơn của kỳ này (nếu đã ghi nhận trước đó)
        $unbilledReadings = MeterReading::where('lease_id', $leaseId)
            ->whereNull('invoice_id')
            ->where('reading_date', '<=', $periodTo)
            ->get()
            ->keyBy('type');

        // 2. CHỈ LẤY CÁC DỊCH VỤ CÓ HIỆU LỰC TẠI KỲ HÓA ĐƠN NÀY ($periodTo)
        $activeServiceItems = $lease->serviceItems->filter(function ($item) use ($periodTo) {
            $effective = $item->effective_date ? \Carbon\Carbon::parse($item->effective_date)->toDateString() : '2000-01-01';
            $expiry = $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->toDateString() : null;

            return $effective <= $periodTo && ($expiry === null || $expiry >= $periodTo);
        });

        // Loop qua danh sách đã lọc thay vì toàn bộ $lease->serviceItems
        foreach ($activeServiceItems as $serviceItem) {
            $type = $serviceItem->service_type instanceof \BackedEnum
                ? $serviceItem->service_type->value
                : $serviceItem->service_type;

            $priceRule = $applicablePrices->first(function ($price) use ($type) {
                $priceType = $price->service_type instanceof \BackedEnum
                    ? $price->service_type->value
                    : $price->service_type;
                return $priceType === $type;
            });

            // 3. Xác định đơn giá dịch vụ
            $unitPrice = $serviceItem->custom_price ?? ($priceRule ? $priceRule->unit_price : 0);
            $quantity = $serviceItem->quantity;
            $freeUnits = 0;

            // 4. Tính toán số lượng miễn phí từ bảng service_prices
            if ($priceRule && $priceRule->free_units > 0) {
                // Lấy ra chuỗi value thực sự của Enum để so sánh
                $freeUnitTypeValue = $priceRule->free_unit_type instanceof \BackedEnum
                    ? $priceRule->free_unit_type->value
                    : $priceRule->free_unit_type;

                if ($freeUnitTypeValue !== 'none') {
                    $memberCount = $freeUnitTypeValue === 'per_person'
                        ? (int) ($lease->occupants_count ?? 1)
                        : 1;

                    $freeUnits = $priceRule->free_units * $memberCount;
                }
            }

            // Các biến bổ sung để phục vụ điện nước có cấu trúc
            $previousReading = null;
            $currentReading = null;
            $isChotRoi = false;
            $isUtility = in_array($type, ['electricity', 'water']);

            if ($isUtility) {
                // TRƯỜNG HỢP LÀ ĐIỆN / NƯỚC
                if ($unbilledReadings->has($type)) {
                    $reading = $unbilledReadings->get($type);
                    $previousReading = $reading->previous_reading;
                    $currentReading = $reading->current_reading;
                    $quantity = max(0, $currentReading - $previousReading);
                    $isChotRoi = true;
                } else {
                    $lastReading = MeterReading::where('lease_id', $leaseId)
                        ->where('type', $type)
                        ->orderByDesc('reading_date')
                        ->orderByDesc('id')
                        ->first();

                    $previousReading = $lastReading ? $lastReading->current_reading : 0;
                    $currentReading = '';
                    $quantity = 0;
                    $isChotRoi = false;
                }

                // Tiền điện/nước = (Số dùng - Số miễn phí) * Đơn giá
                $billableQuantity = max(0, $quantity - $freeUnits);
                $amount = $unitPrice * $billableQuantity;
            } else {
                // TRƯỜNG HỢP CÁC DỊCH VỤ KHÁC (Rác, mạng, xe...)
                $billableQuantity = max(0, $quantity - $freeUnits);
                $amount = $unitPrice * $billableQuantity;
            }

            // Đẩy vào danh sách gợi ý duy nhất 1 dòng cho mỗi loại phí
            $items[] = [
                'service_price_id' => $priceRule ? $priceRule->id : null,
                'charge_type' => $type,
                'description' => 'Tiền ' . mb_strtolower($serviceItem->service_type->label()),
                'unit' => $type === 'electricity' ? 'kWh' : ($type === 'water' ? 'm³' : 'Tháng/Lần'),
                'free_quantity_snapshot' => $freeUnits, // <--- Backend gửi kèm thông số miễn phí để Frontend biết
                'quantity' => $quantity, // Vẫn gửi số lượng tổng thực tế
                'unit_price_snapshot' => $unitPrice,
                'amount' => $amount, // Số tiền sau khi đã trừ miễn phí

                'is_utility' => $isUtility,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'is_chot_roi' => $isChotRoi,
            ];
        }


        // LOGIC TỰ ĐỘNG THÊM TIỀN THẾ CHÂN (CHO HÓA ĐƠN ĐẦU TIÊN)
        // 1. Kiểm tra xem hợp đồng này đã có hóa đơn nào chưa (bỏ qua hóa đơn đã hủy)
        $hasInvoice = \App\Models\Invoice::where('lease_id', $lease->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        // 2. Nếu là hóa đơn đầu tiên (chưa từng tạo) và hợp đồng có yêu cầu tiền cọc
        if (!$hasInvoice && $lease->deposit > 0) {

            // Tìm số tiền khách đã cọc (Chỉ lấy đúng phiếu cọc của hợp đồng này)
            $reservationDeposit = \App\Models\RoomReservation::where('lease_id', $lease->id)
                ->where('status', 'completed')
                ->sum('deposit_amount');

            // Tính số dư cọc cần thu
            $remainingDeposit = (int)$lease->deposit - $reservationDeposit;

            // Nếu số tiền phải thu lớn hơn 0 thì nhét vào mảng gợi ý (Frontend sẽ hiện vào mục Dịch vụ khác)
            if ($remainingDeposit > 0) {
                $items[] = [
                    'charge_type'            => 'deposit',
                    'description'            => 'Tiền thế chân (đã trừ: ' .$reservationDeposit.'đ)',
                    'unit'                   => 'Lần',
                    'quantity'               => 1,
                    'unit_price_snapshot'    => $remainingDeposit,
                    'free_quantity_snapshot' => 0,
                    'amount'                 => $remainingDeposit,
                    'is_utility'             => false,
                    'is_chot_roi'            => false
                ];
            }
        }

        return [
            'lease_id' => $lease->id,
            'room_name' => $lease->room->name,
            'tenant_name' => $lease->tenant->full_name,
            'previous_debt_amount' => 0,
            'suggested_items' => $items,
        ];
    }


    /**
     * Sinh mã hóa đơn.
     *
     * Có thể tùy chỉnh theo format bạn muốn.
     * Ví dụ hiện tại: HD-202606-R12-A1B2
     */
    private function generateInvoiceCode(int $roomId): string
    {
        do {
            $code = sprintf(
                'HD-%s-R%s-%s',
                now()->format('Ym'),
                $roomId,
                Str::upper(Str::random(4))
            );
        } while (Invoice::query()->where('invoice_code', $code)->exists());

        return $code;
    }
}
