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

class DashboardService
{
    /**
     * Lấy toàn bộ dữ liệu thống kê cho màn hình Dashboard
     */
    public function getDashboardData(int $userId): array
    {
        // Lấy trước danh sách ID khu nhà của chủ trọ để dùng chung cho các câu query tối ưu hơn
        $propertyIds = Property::where('user_id', $userId)->pluck('id')->toArray();

        return [
            'overview' => $this->getOverviewStats($propertyIds),
            'financial_chart' => $this->getFinancialChart($propertyIds),
            'pending_tasks' => $this->getPendingTasks($userId, $propertyIds),
        ];
    }

    /**
     * 1. Thống kê tổng quan (Khu nhà, phòng, tỷ lệ lấp đầy)
     */
    private function getOverviewStats(array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return [
                'total_properties' => 0,
                'total_rooms' => 0,
                'occupied_rooms' => 0,
                'available_rooms' => 0,
                'maintenance_rooms' => 0,
                'occupancy_rate' => 0,
            ];
        }

        $totalProperties = count($propertyIds);

        // Đếm số lượng phòng group theo trạng thái (tối ưu chỉ bằng 1 câu query)
        $roomsStat = Room::whereIn('property_id', $propertyIds)
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $occupied = $roomsStat['occupied'] ?? 0;
        $available = $roomsStat['available'] ?? 0;
        $maintenance = $roomsStat['maintenance'] ?? 0;
        $totalRooms = array_sum($roomsStat);

        $occupancyRate = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;

        return [
            'total_properties' => $totalProperties,
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupied,
            'available_rooms' => $available,
            'maintenance_rooms' => $maintenance,
            'occupancy_rate' => $occupancyRate,
        ];
    }

    /**
     * 2. Biểu đồ tài chính 6 tháng gần nhất
     */
    private function getFinancialChart(array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return [];
        }

        $months = [];
        // Tạo khung dữ liệu cho 6 tháng gần nhất (bao gồm tháng hiện tại)
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m'); // Khóa chuẩn dùng để match với CSDL
            $label = $date->format('m/Y'); // Nhãn hiển thị cho Front-end (VD: 01/2024)

            $months[$key] = [
                'month' => $label,
                'income' => 0,
                'expense' => 0,
                'profit' => 0,
            ];
        }

        $startDate = now()->subMonths(5)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Lấy tổng thu chi group theo tháng và direction
        $transactions = FinancialTransaction::whereIn('property_id', $propertyIds)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            // Dùng DATE_FORMAT của MySQL để cắt lấy năm-tháng
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month_key, direction, SUM(amount) as total")
            ->groupBy('month_key', 'direction')
            ->get();

        // Đắp dữ liệu CSDL vào khung đã tạo
        foreach ($transactions as $tx) {
            $key = $tx->month_key;
            if (isset($months[$key])) {
                if ($tx->direction === 'income') {
                    $months[$key]['income'] = (int) $tx->total;
                } else {
                    $months[$key]['expense'] = (int) $tx->total;
                }
                // Cập nhật lợi nhuận = Tổng thu - Tổng chi
                $months[$key]['profit'] = $months[$key]['income'] - $months[$key]['expense'];
            }
        }

        // Trả về mảng index (không dùng key dạng Y-m nữa để React dễ map)
        return array_values($months);
    }

    /**
     * 3. Nhắc việc và Cảnh báo
     */
    private function getPendingTasks(int $userId, array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return [
                'unpaid_invoices_count' => 0,
                'unpaid_invoices_total' => 0,
                'pending_incidents_count' => 0,
                'expiring_leases_count' => 0,
            ];
        }

        // 3.1: Hóa đơn chưa thu (Issued và còn nợ)
        $unpaidInvoices = Invoice::whereHas('lease.room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', 'issued')
            ->where('remaining_amount', '>', 0)
            ->selectRaw("COUNT(*) as count, SUM(remaining_amount) as total")
            ->first();

        // 3.2: Sự cố chờ xử lý (Pending hoặc Processing)
        $pendingIncidentsCount = Incident::whereIn('property_id', $propertyIds)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        // 3.3: Hợp đồng sắp hết hạn (trong 30 ngày tới)
        $expiringLeasesCount = Lease::whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        return [
            'unpaid_invoices_count' => (int) ($unpaidInvoices->count ?? 0),
            'unpaid_invoices_total' => (int) ($unpaidInvoices->total ?? 0),
            'pending_incidents_count' => $pendingIncidentsCount,
            'expiring_leases_count' => $expiringLeasesCount,
        ];
    }
}
