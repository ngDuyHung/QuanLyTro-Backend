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
    public function previewLedger(int $userId, ?int $propertyId, string $periodType, int $year, ?int $month): array
    {
        // 1. Lấy tất cả phiếu THU, ĐÃ XÁC NHẬN, và ĐƯỢC TÍNH LÀ DOANH THU
        $query = FinancialTransaction::query()
            ->with(['allocations.invoice.items']) // Eager load để lấy chi tiết hóa đơn
            ->whereHas('property', function ($q) use ($userId): void {
                $q->where('user_id', $userId);
            })
            ->income()
            ->confirmed()
            ->where('accounting_type', 'revenue')
            ->whereYear('transaction_date', $year);

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        // Nếu chốt theo tháng thì lọc thêm tháng
        if ($periodType === 'month' && $month) {
            $query->whereMonth('transaction_date', $month);
        }

        // Sổ kế toán phải sắp xếp theo trình tự thời gian
        $transactions = $query->orderBy('transaction_date', 'asc')->get();

        $totalRevenue = 0;
        $ledgerRows = [];

        foreach ($transactions as $tx) {
            $totalRevenue += $tx->amount;

            // Xây dựng chuỗi Diễn giải từ các item của hóa đơn
            $itemNames = [];
            if ($tx->allocations->isNotEmpty()) {
                foreach ($tx->allocations as $allocation) {
                    if ($allocation->invoice) {
                        foreach ($allocation->invoice->items as $item) {
                            // Lấy tên các khoản thu (VD: Tiền phòng, Điện, Nước)
                            $itemNames[] = $item->description;
                        }
                    }
                }
            }

            // Nếu có chi tiết item thì nối chuỗi lại (loại bỏ các tên trùng lặp)
            // Nếu không có (nhập thủ công) thì lấy description gốc của phiếu thu
            $description = empty($itemNames)
                ? $tx->description
                : implode(', ', array_unique($itemNames));

            // Gom vào mảng dữ liệu tạm
            $ledgerRows[] = [
                'transaction_date' => $tx->transaction_date ? Carbon::parse($tx->transaction_date)->format('Y-m-d') : null,
                'transaction_code' => $tx->transaction_code,
                'description'      => $description,
                'amount'           => (int) $tx->amount,
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
