<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\Tenant;
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
            'collection_status' => $this->getCollectionStatus($propertyIds),
            'expiring_leases' => $this->getExpiringLeasesList($propertyIds),
        ];
    }

    //  Hàm tính toán tình hình thu tiền tháng
    private function getCollectionStatus(array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return $this->emptyCollectionStatus();
        }

        // Lấy từ ngày đầu tháng đến cuối tháng hiện tại
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        // Lấy tổng total_amount và paid_amount group theo trạng thái của hóa đơn phát hành tháng này
        $stats = Invoice::whereHas('lease.room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', '!=', 'draft') // Bỏ qua hóa đơn nháp
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->selectRaw("status, SUM(total_amount) as sum_total, SUM(paid_amount) as sum_paid")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $paid = (int) ($stats['paid']->sum_total ?? 0);
        $partially_paid = (int) ($stats['partially_paid']->sum_total ?? 0);
        $issued = (int) ($stats['issued']->sum_total ?? 0); // Chưa thu
        $cancelled = (int) ($stats['cancelled']->sum_total ?? 0); // Đã hủy

        // Tổng kỳ vọng thu trong tháng = Tổng giá trị các hóa đơn
        $totalExpected = $paid + $partially_paid + $issued + $cancelled;

        // Tổng tiền THỰC TẾ đã cầm trong tay của các hóa đơn tháng này
        $collectedTotal = (int) $stats->sum('sum_paid');

        // Hàm tính phần trăm an toàn
        $calcPercent = fn($val) => $totalExpected > 0 ? round(($val / $totalExpected) * 100) : 0;

        return [
            'month_label' => now()->format('m/Y'),
            'total_expected' => $totalExpected,
            'collected_total' => $collectedTotal,
            'statuses' => [
                'paid' => ['amount' => $paid, 'percent' => $calcPercent($paid)],
                'partially_paid' => ['amount' => $partially_paid, 'percent' => $calcPercent($partially_paid)],
                'issued' => ['amount' => $issued, 'percent' => $calcPercent($issued)],
                'cancelled' => ['amount' => $cancelled, 'percent' => $calcPercent($cancelled)],
            ]
        ];
    }

    private function emptyCollectionStatus(): array
    {
        return [
            'month_label' => now()->format('m/Y'),
            'total_expected' => 0,
            'collected_total' => 0,
            'statuses' => [
                'paid' => ['amount' => 0, 'percent' => 0],
                'partially_paid' => ['amount' => 0, 'percent' => 0],
                'issued' => ['amount' => 0, 'percent' => 0],
                'cancelled' => ['amount' => 0, 'percent' => 0],
            ]
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
                'total_leases' => 0,
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
        $totalLeases = Lease::whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', 'active')
            ->count();

        return [
            'total_properties' => $totalProperties,
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupied,
            'available_rooms' => $available,
            'maintenance_rooms' => $maintenance,
            'occupancy_rate' => $occupancyRate,
            'total_leases' => $totalLeases,
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
            ->where('status', 'confirmed')
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
                'overdue_invoices_count' => 0,
                'overdue_invoices_total' => 0,
                'due_soon_invoices_count' => 0,
                'due_soon_invoices_total' => 0,
                'pending_incidents_count' => 0,
                'expiring_leases_count' => 0,
            ];
        }

        $now = now()->toDateString();
        $threeDaysLater = now()->addDays(3)->toDateString();

        // 3.1: Hóa đơn chưa thu (Gom nhóm Quá hạn, Sắp đến hạn và Tổng bằng 1 câu Query duy nhất)
        $invoiceStats = Invoice::whereHas('lease.room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', 'issued')
            ->where('remaining_amount', '>', 0)
            ->selectRaw("
                COUNT(id) as total_count,
                SUM(remaining_amount) as total_amount,
                SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN due_date < ? THEN remaining_amount ELSE 0 END) as overdue_amount,
                SUM(CASE WHEN due_date >= ? AND due_date <= ? THEN 1 ELSE 0 END) as due_soon_count,
                SUM(CASE WHEN due_date >= ? AND due_date <= ? THEN remaining_amount ELSE 0 END) as due_soon_amount
            ", [$now, $now, $now, $threeDaysLater, $now, $threeDaysLater])
            ->first();

        // 3.2: Sự cố chờ xử lý
        $pendingIncidentsCount = Incident::whereIn('property_id', $propertyIds)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        // 3.3: Hợp đồng sắp hết hạn (trong 30 ngày tới)
        $expiringLeasesCount = Lease::whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', 'active')
            ->whereBetween('end_date', [$now, now()->addDays(30)->toDateString()])
            ->count();

        return [
            // Dữ liệu dùng chung
            'unpaid_invoices_count' => (int) ($invoiceStats->total_count ?? 0),
            'unpaid_invoices_total' => (int) ($invoiceStats->total_amount ?? 0),

            // Dữ liệu mới thêm cho khối Hóa Đơn Cần Chú Ý
            'overdue_invoices_count' => (int) ($invoiceStats->overdue_count ?? 0),
            'overdue_invoices_total' => (int) ($invoiceStats->overdue_amount ?? 0),
            'due_soon_invoices_count' => (int) ($invoiceStats->due_soon_count ?? 0),
            'due_soon_invoices_total' => (int) ($invoiceStats->due_soon_amount ?? 0),

            'pending_incidents_count' => $pendingIncidentsCount,
            'expiring_leases_count' => $expiringLeasesCount,
        ];
    }

    /**
     * Lấy chi tiết danh sách hợp đồng sắp hết hạn (trong vòng 30 ngày)
     */
    private function getExpiringLeasesList(array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return [];
        }

        $now = now()->startOfDay();
        $thirtyDaysLater = now()->addDays(30)->endOfDay();

        $leases = Lease::with(['room.property', 'tenant'])
            ->whereHas('room', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$now, $thirtyDaysLater])
            ->orderBy('end_date', 'asc')
            ->get();

        return $leases->map(function ($lease) use ($now) {
            $endDate = \Carbon\Carbon::parse($lease->end_date)->startOfDay();
            $daysLeft = (int) $now->diffInDays($endDate, false);

            return [
                'id' => $lease->id,
                'room_name' => $lease->room->name ?? 'N/A',
                'property_name' => $lease->room->property->name ?? 'N/A',
                'tenant_name' => $lease->tenant->full_name ?? 'N/A',
                'end_date' => $endDate->format('d/m/Y'),
                'days_left' => max(0, $daysLeft), // Đảm bảo không bị số âm
            ];
        })->toArray();
    }


    /**
     * Lấy toàn bộ dữ liệu tổng quan cho Dashboard của Khách thuê
     */
    public function getTenantDashboardData(int $userId): array
    {
        // 1. Tìm thông tin khách thuê từ tài khoản đăng nhập
        $tenant = Tenant::where('user_id', $userId)->first();

        if (!$tenant) {
            return ['error' => 'Tài khoản của bạn chưa được liên kết với hồ sơ khách thuê nào.'];
        }

        // 2. Lấy hợp đồng đang hoạt động kèm thông tin Phòng và Khu nhà
        $activeLease = Lease::with(['room.property'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        if (!$activeLease) {
            return ['error' => 'Bạn hiện không có hợp đồng thuê phòng nào đang hoạt động.'];
        }

        $roomId = $activeLease->room_id;
        $propertyId = $activeLease->room->property_id;

        // 3. Truy vấn các dữ liệu liên quan

        // A. Hóa đơn: Lấy hóa đơn nợ/chưa thanh toán
        $unpaidInvoices = Invoice::where('lease_id', $activeLease->id)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->get();

        // A1. Lấy 5 hóa đơn đã phát hành gần nhất (có thể đã thanh toán hoặc chưa)
        $recentInvoices = Invoice::where('lease_id', $activeLease->id)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue', 'paid'])
            ->orderBy('issue_date', 'desc')
            ->limit(5)
            ->get();

        // B. Điện nước: Lấy 2 bản ghi chỉ số gần nhất (thường là 1 điện, 1 nước của kỳ mới nhất)
        $recentUtilities = MeterReading::where('lease_id', $activeLease->id)
            ->orderBy('reading_date', 'desc')
            ->limit(4)
            ->get();

        // C. Sự cố: Các sự cố đang mở hoặc vừa xử lý của khách thuê này
        $recentIncidents = Incident::where('reported_by_tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // D. Thông báo: Lấy thông báo mới nhất gửi cho user này (hoặc thông báo chung của khu nhà/phòng)
        $notifications = Notification::where(function ($query) use ($userId, $propertyId, $roomId) {
            $query->where('user_id', $userId) // Thông báo cá nhân
                ->orWhere(function ($q) use ($propertyId) { // Thông báo toàn khu
                    $q->where('target_type', 'property')
                        ->where('target_id', $propertyId);
                })
                ->orWhere(function ($q) use ($roomId) { // Thông báo toàn phòng
                    $q->where('target_type', 'room')
                        ->where('target_id', $roomId);
                });
        })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Lấy ảnh đại diện của phòng (is_cover = 1), nếu không có thì lấy ảnh đầu tiên
        $coverImage = RoomImage::where('room_id', $roomId)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->first();

        // 4. Trả về cấu trúc JSON phân nhóm rõ ràng
        return [
            'room_info' => [
                'property_name' => $activeLease->room->property->name,
                'room_name'     => $activeLease->room->name,
                'address'       => $activeLease->room->property->address,
                'room_price'    => $activeLease->room_price,
                'floor_number' => $activeLease->room->floor_number,
                'img_url' => $coverImage?->image_url ? asset('storage/' . ltrim($coverImage->image_url)) : null,
            ],
            'lease_info' => [
                'id'         => $activeLease->id,
                'start_date' => $activeLease->start_date,
                'end_date'   => $activeLease->end_date,
                'deposit'    => $activeLease->deposit,
                'occupants_count' => $activeLease->occupants_count,
            ],
            'unpaid_invoices' => $unpaidInvoices,
            'recent_invoices' => $recentInvoices,
            'recent_utilities' => $recentUtilities,
            'recent_incidents' => $recentIncidents,
            'notifications'    => $notifications,
        ];
    }
}
