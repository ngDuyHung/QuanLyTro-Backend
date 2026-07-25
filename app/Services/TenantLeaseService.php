<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lease;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantLeaseService
{
    /**
     * Lấy danh sách hợp đồng (đang thuê hoặc đã kết thúc) của người dùng hiện tại
     */
    public function getTenantLeases(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Lease::with([
            'room:id,name,property_id',
            'room.property:id,name,address',
            'tenant:id,full_name,phone',
            'serviceItems'
        ])
        ->where(function (Builder $query) use ($userId) {
            // Lấy hợp đồng nếu user là người đứng tên
            $query->whereHas('tenant', fn($q) => $q->where('user_id', $userId))
                  // Hoặc user là thành viên ở ghép
                  ->orWhereHas('members.tenant', fn($q) => $q->where('user_id', $userId));
        });

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('start_date')->paginate($perPage);
    }

    /**
     * Lấy chi tiết một hợp đồng
     */
    public function getTenantLease(int $leaseId, int $userId): Lease
    {
        return Lease::with([
            'room.property:id,name,address,user_id',
            'tenant',
            'members.tenant',
            'serviceItems',
            'invoices:id,lease_id,invoice_code,status,total_amount,remaining_amount',
        ])
        ->where(function (Builder $query) use ($userId) {
            $query->whereHas('tenant', fn($q) => $q->where('user_id', $userId))
                  ->orWhereHas('members.tenant', fn($q) => $q->where('user_id', $userId));
        })
        ->findOrFail($leaseId);
    }
}