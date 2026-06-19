<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
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

                'invoice_code' => $this->generateInvoiceCode($lease->room_id),
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

            $amount = array_key_exists('amount', $item)
                ? (int) $item['amount']
                : (int) round($quantity * $unitPriceSnapshot);

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