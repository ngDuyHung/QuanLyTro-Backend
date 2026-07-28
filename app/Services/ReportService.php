<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Lấy danh sách ID khu nhà hợp lệ cho việc báo cáo.
     * Nếu không truyền propertyId, lấy tất cả khu nhà của chủ trọ.
     */
    private function getValidPropertyIds(int $userId, ?int $propertyId): array
    {
        $query = Property::where('user_id', $userId);
        if ($propertyId) {
            $query->where('id', $propertyId);
        }
        return $query->pluck('id')->toArray();
    }

    /**
     * 1. BÁO CÁO TÀI CHÍNH & DÒNG TIỀN (Financial Report)
     */
    public function getFinancialReport(int $userId, ?int $propertyId, string $fromDate, string $toDate): array
    {
        $propertyIds = $this->getValidPropertyIds($userId, $propertyId);
        if (empty($propertyIds)) return $this->emptyFinancialData();

        $startOfDay = Carbon::parse($fromDate)->startOfDay()->toDateTimeString();
        $endOfDay = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        // Gộp thành 1 query duy nhất (thay vì 3), lọc chỉ doanh thu/chi phí THẬT
        // Kèm COUNT(id) để tính số giao dịch & giá trị trung bình mỗi giao dịch
        $rows = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'confirmed')
            ->whereBetween('transaction_date', [$startOfDay, $endOfDay])
            ->whereIn('accounting_type', ['revenue', 'expense'])
            ->selectRaw('direction, category, SUM(amount) as total, COUNT(id) as cnt')
            ->groupBy('direction', 'category')
            ->get();

        $totalIncome = (int) $rows->where('direction', 'income')->sum('total');
        $totalExpense = (int) $rows->where('direction', 'expense')->sum('total');
        $incomeCount = (int) $rows->where('direction', 'income')->sum('cnt');
        $expenseCount = (int) $rows->where('direction', 'expense')->sum('cnt');

        $profit = $totalIncome - $totalExpense;
        $profitMargin = $totalIncome > 0 ? round($profit / $totalIncome * 100, 1) : 0.0;
        $avgIncomeTransaction = $incomeCount > 0 ? (int) round($totalIncome / $incomeCount) : 0;
        $avgExpenseTransaction = $expenseCount > 0 ? (int) round($totalExpense / $expenseCount) : 0;

        $incomeBreakdown = $rows->where('direction', 'income')
            ->mapWithKeys(fn($r) => [$r->category => (int) $r->total])->toArray();
        $expenseBreakdown = $rows->where('direction', 'expense')
            ->mapWithKeys(fn($r) => [$r->category => (int) $r->total])->toArray();

        // Khối riêng: tiền giữ hộ (cọc) - KHÔNG cộng vào lợi nhuận, chỉ để tham khảo dòng tiền
        $depositRows = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'confirmed')
            ->whereBetween('transaction_date', [$startOfDay, $endOfDay])
            ->whereIn('accounting_type', ['liability_in', 'liability_out'])
            ->selectRaw('direction, SUM(amount) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        // --- SO SÁNH VỚI KỲ TRƯỚC (cùng độ dài số ngày, liền kề ngay trước fromDate) ---
        $fromCarbon = Carbon::parse($fromDate);
        $toCarbon = Carbon::parse($toDate);
        $periodDays = $fromCarbon->diffInDays($toCarbon) + 1;

        $prevToDate = $fromCarbon->copy()->subDay()->endOfDay();
        $prevFromDate = $fromCarbon->copy()->subDays($periodDays)->startOfDay();

        $prevRows = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'confirmed')
            ->whereBetween('transaction_date', [$prevFromDate->toDateTimeString(), $prevToDate->toDateTimeString()])
            ->whereIn('accounting_type', ['revenue', 'expense'])
            ->selectRaw('direction, SUM(amount) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        $prevIncome = (int) ($prevRows['income'] ?? 0);
        $prevExpense = (int) ($prevRows['expense'] ?? 0);
        $prevProfit = $prevIncome - $prevExpense;

        $calcChangePercent = function (int $current, int $previous): float {
            if ($previous === 0) {
                return $current > 0 ? 100.0 : 0.0;
            }
            return round((($current - $previous) / abs($previous)) * 100, 1);
        };

        // --- DOANH THU THEO TỪNG KHU NHÀ (chỉ tính khi xem "Tất cả khu nhà" và chủ trọ có > 1 khu) ---
        $propertyBreakdown = [];
        if (!$propertyId && count($propertyIds) > 1) {
            $propertyRows = FinancialTransaction::whereIn('property_id', $propertyIds)
                ->where('status', 'confirmed')
                ->whereBetween('transaction_date', [$startOfDay, $endOfDay])
                ->whereIn('accounting_type', ['revenue', 'expense'])
                ->selectRaw('property_id, direction, SUM(amount) as total')
                ->groupBy('property_id', 'direction')
                ->get()
                ->groupBy('property_id');

            $propertyNames = Property::whereIn('id', $propertyIds)->pluck('name', 'id');

            foreach ($propertyRows as $pid => $items) {
                $inc = (int) $items->where('direction', 'income')->sum('total');
                $exp = (int) $items->where('direction', 'expense')->sum('total');
                $propertyBreakdown[] = [
                    'property_id'   => (int) $pid,
                    'property_name' => $propertyNames[$pid] ?? 'N/A',
                    'total_income'  => $inc,
                    'total_expense' => $exp,
                    'profit'        => $inc - $exp,
                ];
            }

            usort($propertyBreakdown, fn($a, $b) => $b['profit'] <=> $a['profit']);
        }

        return [
            'overview' => [
                'total_income'  => $totalIncome,
                'total_expense' => $totalExpense,
                'profit'        => $profit,
                'profit_margin' => $profitMargin,
                'income_transaction_count'  => $incomeCount,
                'expense_transaction_count' => $expenseCount,
                'avg_income_transaction'    => $avgIncomeTransaction,
                'avg_expense_transaction'   => $avgExpenseTransaction,
            ],
            'income_breakdown'  => $incomeBreakdown,
            'expense_breakdown' => $expenseBreakdown,
            'deposit_holding' => [
                'received'    => (int) ($depositRows['income'] ?? 0),
                'refunded'    => (int) ($depositRows['expense'] ?? 0),
            ],
            'comparison' => [
                'previous_total_income'  => $prevIncome,
                'previous_total_expense' => $prevExpense,
                'previous_profit'        => $prevProfit,
                'income_change_percent'  => $calcChangePercent($totalIncome, $prevIncome),
                'expense_change_percent' => $calcChangePercent($totalExpense, $prevExpense),
                'profit_change_percent'  => $calcChangePercent($profit, $prevProfit),
                'previous_from_date' => $prevFromDate->format('Y-m-d'),
                'previous_to_date'   => $prevToDate->format('Y-m-d'),
            ],
            'property_breakdown' => $propertyBreakdown,
        ];
    }

    private function emptyFinancialData(): array
    {
        return [
            'overview' => [
                'total_income' => 0,
                'total_expense' => 0,
                'profit' => 0,
                'profit_margin' => 0,
                'income_transaction_count' => 0,
                'expense_transaction_count' => 0,
                'avg_income_transaction' => 0,
                'avg_expense_transaction' => 0,
            ],
            'income_breakdown' => [],
            'expense_breakdown' => [],
            'deposit_holding' => ['received' => 0, 'refunded' => 0],
            'comparison' => [
                'previous_total_income' => 0,
                'previous_total_expense' => 0,
                'previous_profit' => 0,
                'income_change_percent' => 0,
                'expense_change_percent' => 0,
                'profit_change_percent' => 0,
                'previous_from_date' => null,
                'previous_to_date' => null,
            ],
            'property_breakdown' => [],
        ];
    }

    /**
     * 2. BÁO CÁO SỔ QUỸ (Ledger Report)
     * Đối soát tiền vào ra liên tục
     */
    public function getLedgerReport(int $userId, ?int $propertyId, string $fromDate, string $toDate): array
    {
        $propertyIds = $this->getValidPropertyIds($userId, $propertyId);
        if (empty($propertyIds)) return [];

        $startOfDay = Carbon::parse($fromDate)->startOfDay()->toDateTimeString();
        $endOfDay = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        // Tính TỒN ĐẦU KỲ (Trước ngày fromDate)
        $openingBalances = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'confirmed')
            ->where('transaction_date', '<', $startOfDay)
            ->selectRaw('direction, SUM(amount) as total_amount')
            ->groupBy('direction')
            ->pluck('total_amount', 'direction');

        $openingBalance = (int)($openingBalances['income'] ?? 0) - (int)($openingBalances['expense'] ?? 0);

        // Danh sách giao dịch phát sinh TRONG KỲ
        $transactions = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'confirmed')
            ->whereBetween('transaction_date', [$startOfDay, $endOfDay])
            ->orderBy('transaction_date', 'asc') // Sắp xếp cũ đến mới để cộng dồn số dư
            ->orderBy('id', 'asc') // Tiêu chí phụ, tránh đảo thứ tự khi 2 GD trùng giây
            ->get(['id', 'transaction_code', 'transaction_date', 'direction', 'amount', 'category', 'method', 'description', 'accounting_type']);

        $totalIncomeInPeriod = 0;
        $totalExpenseInPeriod = 0;

        // Tách riêng phần "tiền của mình" (revenue/expense thật) và phần "tiền giữ hộ" (cọc)
        // phát sinh TRONG KỲ, để phân biệt với Tổng Thu/Chi gộp chung phía trên.
        $operatingIncomeInPeriod = 0;   // Doanh thu thật thu được trong kỳ
        $depositReceivedInPeriod = 0;   // Tiền cọc thu mới trong kỳ
        $operatingExpenseInPeriod = 0;  // Chi phí thực chi ra trong kỳ
        $depositRefundedInPeriod = 0;   // Tiền cọc hoàn trả khách trong kỳ

        $details = [];
        $runningBalance = $openingBalance;

        foreach ($transactions as $tx) {
            $isDeposit = in_array($tx->accounting_type, ['liability_in', 'liability_out'], true);

            if ($tx->direction === 'income') {
                $runningBalance += $tx->amount;
                $totalIncomeInPeriod += $tx->amount;
                $isDeposit ? $depositReceivedInPeriod += $tx->amount : $operatingIncomeInPeriod += $tx->amount;
            } else {
                $runningBalance -= $tx->amount;
                $totalExpenseInPeriod += $tx->amount;
                $isDeposit ? $depositRefundedInPeriod += $tx->amount : $operatingExpenseInPeriod += $tx->amount;
            }

            $details[] = [
                'id' => $tx->id,
                'date' => Carbon::parse($tx->transaction_date)->format('Y-m-d H:i'),
                'code' => $tx->transaction_code,
                'description' => $tx->description,
                'method' => $tx->method,
                'income' => $tx->direction === 'income' ? $tx->amount : 0,
                'expense' => $tx->direction === 'expense' ? $tx->amount : 0,
                'balance' => $runningBalance,
                'is_deposit' => $isDeposit,
            ];
        }

        // Tính số tiền cọc (giữ hộ) đang nằm TRONG số dư tồn quỹ tính đến hết toDate (lũy kế toàn thời gian,
        // không giới hạn theo fromDate, vì đây là nghĩa vụ hiện tại chứ không phải phát sinh trong kỳ)
        $depositBalances = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'confirmed')
            ->where('transaction_date', '<=', $endOfDay)
            ->whereIn('accounting_type', ['liability_in', 'liability_out'])
            ->selectRaw('direction, SUM(amount) as total_amount')
            ->groupBy('direction')
            ->pluck('total_amount', 'direction');

        $depositHeld = (int) ($depositBalances['income'] ?? 0) - (int) ($depositBalances['expense'] ?? 0);
        $freeCashBalance = $runningBalance - $depositHeld;

        return [
            'summary' => [
                'opening_balance'    => $openingBalance,
                'total_income'       => $totalIncomeInPeriod,
                'total_expense'      => $totalExpenseInPeriod,
                'closing_balance'    => $runningBalance,
                // Lũy kế toàn thời gian tới hết kỳ (số dư nợ cọc hiện tại - dạng bảng cân đối)
                'deposit_held'       => $depositHeld,
                'free_cash_balance'  => $freeCashBalance,
                // Phát sinh trong kỳ đang xem (dạng dòng tiền - để tách khỏi total_income/total_expense)
                'operating_income'   => $operatingIncomeInPeriod,
                'deposit_received'   => $depositReceivedInPeriod,
                'operating_expense'  => $operatingExpenseInPeriod,
                'deposit_refunded'   => $depositRefundedInPeriod,
            ],
            'transactions' => $details,
        ];
    }

    /**
     * 3. BÁO CÁO CÔNG NỢ (Debt Report)
     * Lưu ý: Công nợ thường là số liệu tại thời điểm HIỆN TẠI, không phụ thuộc fromDate/toDate.
     */
    public function getDebtReport(int $userId, ?int $propertyId): array
    {
        $propertyIds = $this->getValidPropertyIds($userId, $propertyId);
        if (empty($propertyIds)) return [];

        // Thống kê tuổi nợ bằng SQL chuẩn (sử dụng DATEDIFF để tính khoảng cách ngày)
        $agingStats = Invoice::whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('remaining_amount', '>', 0)
            ->selectRaw("
                SUM(remaining_amount) as total_debt,
                SUM(CASE WHEN due_date IS NULL OR due_date >= CURRENT_DATE THEN remaining_amount ELSE 0 END) as in_term,
                SUM(CASE WHEN due_date IS NOT NULL AND DATEDIFF(CURRENT_DATE, due_date) BETWEEN 1 AND 15 THEN remaining_amount ELSE 0 END) as overdue_1_15,
                SUM(CASE WHEN due_date IS NOT NULL AND DATEDIFF(CURRENT_DATE, due_date) > 15 THEN remaining_amount ELSE 0 END) as overdue_over_15
            ")
            ->first();

        // Lấy top 10 phòng đang nợ nhiều nhất để hiển thị cảnh báo
        $topDebtors = Invoice::with(['room:id,name,property_id', 'room.property:id,name', 'lease.tenant:id,full_name,phone'])
            ->whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('remaining_amount', '>', 0)
            ->selectRaw('lease_id, room_id, SUM(remaining_amount) as total_debt_amount, COUNT(id) as unpaid_invoices_count')
            ->groupBy('lease_id', 'room_id')
            ->orderBy('total_debt_amount', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'room_name'     => $item->room->name ?? 'N/A',
                    'property_name' => $item->room->property->name ?? 'N/A',
                    'tenant_name'   => $item->lease->tenant->full_name ?? 'N/A',
                    'tenant_phone'  => $item->lease->tenant->phone ?? 'N/A',
                    'debt_amount'   => (int)$item->total_debt_amount,
                    'invoice_count' => (int)$item->unpaid_invoices_count,
                ];
            });

        return [
            'aging' => [
                'total_debt'      => (int)($agingStats->total_debt ?? 0),
                'in_term'         => (int)($agingStats->in_term ?? 0),
                'overdue_1_15'    => (int)($agingStats->overdue_1_15 ?? 0),
                'overdue_over_15' => (int)($agingStats->overdue_over_15 ?? 0),
            ],
            'top_debtors' => $topDebtors,
        ];
    }

    /**
     * 4. BÁO CÁO VẬN HÀNH (Occupancy & Operations)
     */
    public function getOccupancyReport(int $userId, ?int $propertyId, string $fromDate, string $toDate): array
    {
        $propertyIds = $this->getValidPropertyIds($userId, $propertyId);
        if (empty($propertyIds)) return [];

        $startDate = Carbon::parse($fromDate)->startOfDay();
        $endDate = Carbon::parse($toDate)->endOfDay();

        // Khách mới dọn vào (hợp đồng có start_date trong kỳ)
        $newLeases = Lease::whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        // Khách trả phòng (hợp đồng có end_date trong kỳ)
        $endedLeases = Lease::whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        // Phân tích Sự cố theo phân loại
        $incidents = Incident::whereIn('property_id', $propertyIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('category, COUNT(id) as total')
            ->groupBy('category')
            ->get()
            // Dùng getRawOriginal() để đảm bảo lấy ra string thay vì object Enum
            ->mapWithKeys(fn($item) => [$item->getRawOriginal('category') => (int)$item->total])
            ->toArray();

        //Tỷ lệ lấp đầy hiện tại (snapshot, không phụ thuộc fromDate/toDate)
        $roomStats = Room::whereIn('property_id', $propertyIds)
            ->selectRaw('status, COUNT(id) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalRooms = (int) $roomStats->sum();
        $occupiedRooms = (int) ($roomStats['occupied'] ?? 0);
        $occupancyRate = $totalRooms > 0 ? round($occupiedRooms / $totalRooms * 100, 1) : 0;

        $vacantRooms = Room::whereIn('property_id', $propertyIds)
            ->where('status', 'available')
            ->with('property:id,name')
            ->orderBy('updated_at', 'asc') // Phòng trống lâu nhất lên đầu
            ->limit(10)
            ->get(['id', 'name', 'property_id', 'current_price', 'updated_at'])
            ->map(fn($r) => [
                'room_name'     => $r->name,
                'property_name' => $r->property->name ?? 'N/A',
                'price'         => (int) $r->current_price,
                'vacant_since'  => $r->updated_at?->format('Y-m-d'),
            ]);

        return [
            'occupancy' => [
                'total_rooms'    => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'occupancy_rate' => $occupancyRate,
                'vacant_rooms'   => $vacantRooms,
            ],
            'leases' => [
                'new_leases'   => $newLeases,
                'ended_leases' => $endedLeases,
            ],
            'incidents_breakdown' => $incidents,
        ];
    }
}