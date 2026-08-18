<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\AccountingLedger;
use App\Models\AccountingLedgerDetail;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingLedgerService
{
    /**
     * Xem trước dữ liệu Sổ kế toán (Chưa lưu)
     * Trả về tổng doanh thu và danh sách các dòng chi tiết để Frontend hiển thị Table
     */
    public function previewLedger(int $userId, ?int $propertyId, string $periodType, int $year, ?int $month, bool $includeDeposit = false): array
    {
        $propertyIds = $propertyId
            ? [$propertyId]
            : \App\Models\Property::where('user_id', $userId)->pluck('id')->toArray();

        if (empty($propertyIds)) {
            return ['total_revenue' => 0, 'details' => []];
        }

        $query = FinancialTransaction::query()
            ->select('id', 'transaction_date', 'transaction_code', 'description', 'amount', 'accounting_type')
            ->with([
                'allocations:id,financial_transaction_id,invoice_id',
                'allocations.invoice:id',
                'allocations.invoice.items:id,invoice_id,charge_type,amount,description'
            ])
            ->whereIn('property_id', $propertyIds)
            ->income()
            ->confirmed()
            ->whereYear('transaction_date', $year);

        // LOGIC CHÍNH: Nếu gộp cọc thì lấy cả 2, nếu không thì chỉ lấy doanh thu
        if ($includeDeposit) {
            $query->whereIn('accounting_type', ['revenue', 'liability_in']);
        } else {
            $query->where('accounting_type', 'revenue');
        }

        if ($periodType === 'month' && $month) {
            $query->whereMonth('transaction_date', $month);
        }

        $transactions = $query->orderBy('transaction_date', 'asc')->get();

        $totalRevenue = 0;
        $ledgerRows = [];

        foreach ($transactions as $tx) {
            $amount = (int) $tx->amount; // Số tiền gốc của phiếu thu, KHÔNG TRỪ GÌ CẢ
            $itemNames = [];

            // Xử lý lấy diễn giải (Description) từ chi tiết hóa đơn (nếu có)
            if ($tx->allocations->isNotEmpty()) {
                foreach ($tx->allocations as $allocation) {
                    if ($allocation->invoice && $allocation->invoice->items->isNotEmpty()) {
                        foreach ($allocation->invoice->items as $item) {

                            // Nếu phiếu thu này là Doanh thu, bỏ qua mô tả của dòng "Tiền cọc"
                            if ($tx->accounting_type === 'revenue' && $item->charge_type === 'deposit') {
                                continue;
                            }
                            // Nếu phiếu thu này là Tiền cọc, CHỈ lấy mô tả của dòng "Tiền cọc"
                            if ($tx->accounting_type === 'liability_in' && $item->charge_type !== 'deposit') {
                                continue;
                            }

                            $itemNames[] = $item->description;
                        }
                    }
                }
            }

            $totalRevenue += $amount;

            $formattedDate = $tx->transaction_date
                ? substr((string)$tx->transaction_date, 0, 10)
                : null;

            // Nếu $itemNames rỗng (VD: Phiếu thu cọc giữ chỗ từ RoomReservation không có hóa đơn), 
            // lấy trực tiếp mô tả gốc của phiếu thu ($tx->description)
            $ledgerRows[] = [
                'transaction_date' => $formattedDate,
                'transaction_code' => $tx->transaction_code,
                'description'      => empty($itemNames) ? $tx->description : implode(', ', array_unique($itemNames)),
                'amount'           => $amount,
            ];
        }

        return [
            'total_revenue' => $totalRevenue,
            'details'       => $ledgerRows,
        ];
    }

    /**
     * Thực hiện chốt sổ và lưu vào Database (Nhận dữ liệu tùy chỉnh từ Frontend)
     */
    public function lockLedger(int $userId, array $data): AccountingLedger
    {
        return DB::transaction(function () use ($userId, $data) {
            // 1. Kiểm tra xem kỳ này đã chốt chưa để tránh chốt đè
            $exists = AccountingLedger::query()
                ->where('user_id', $userId)
                ->where('property_id', $data['property_id'] ?? null)
                ->where('period_type', $data['period_type'])
                ->where('period_year', $data['period_year'])
                ->where('period_month', $data['period_month'] ?? null)
                ->exists();

            if ($exists) {
                throw new BusinessException('Kỳ kế toán này đã được chốt sổ. Bạn không thể chốt đè lên kỳ cũ.');
            }

            // 2. TỰ ĐỘNG TÍNH LẠI TỔNG DOANH THU TỪ MẢNG FRONTEND GỬI LÊN
            // (Tuyệt đối an toàn, không phụ thuộc vào con số tổng FE gửi)
            $totalRevenue = 0;
            foreach ($data['details'] as $item) {
                $totalRevenue += (int) $item['amount'];
            }

            // 3. BẢNG MẸ: Tạo record chốt sổ
            $ledger = AccountingLedger::create([
                'user_id'       => $userId,
                'property_id'   => $data['property_id'] ?? null,
                'period_type'   => $data['period_type'],
                'period_year'   => $data['period_year'],
                'period_month'  => $data['period_month'] ?? null,
                'total_revenue' => $totalRevenue,
                'note'          => $data['note'] ?? null,
                'created_by'    => $userId,
            ]);

            // 4. BẢNG CON: Lấy trực tiếp mảng 'details' do chủ trọ đã chỉnh sửa trên màn hình
            $now = now();
            $detailRecords = [];

            foreach ($data['details'] as $row) {
                $detailRecords[] = [
                    'accounting_ledger_id' => $ledger->id,
                    'transaction_date'     => $row['transaction_date'] ?? null,
                    'transaction_code'     => $row['transaction_code'] ?? null,
                    'description'          => $row['description'] ?? null,
                    'amount'               => (int) $row['amount'],
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }

            // Thực hiện Bulk Insert để tối ưu hiệu suất
            if (!empty($detailRecords)) {
                foreach (array_chunk($detailRecords, 500) as $chunk) {
                    AccountingLedgerDetail::insert($chunk);
                }
            }

            return $ledger->load('details');
        });
    }
}
