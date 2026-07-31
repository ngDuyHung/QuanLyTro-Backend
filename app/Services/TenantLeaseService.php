<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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

    /**
     * Khách thuê đăng ký trả phòng (cập nhật ngày dự kiến dọn đi)
     */
    public function registerCheckoutNotice(int $leaseId, string $moveOutDate, int $userId): Lease
    {
        // 1. Lấy hợp đồng ra kèm quyền kiểm tra (phải là người thuê hoặc ở ghép)
        $lease = Lease::where(function (Builder $query) use ($userId) {
            $query->whereHas('tenant', fn($q) => $q->where('user_id', $userId))
                ->orWhereHas('members.tenant', fn($q) => $q->where('user_id', $userId));
        })->findOrFail($leaseId);

        // 2. Validate ràng buộc kinh doanh
        if (!$lease->status->isActive()) {
            throw new BusinessException('Chỉ có thể đăng ký trả phòng cho hợp đồng đang có hiệu lực.');
        }

        if ($lease->move_out_notice_date !== null) {
            throw new BusinessException('Bạn đã đăng ký trả phòng cho hợp đồng này rồi.');
        }

        if (now()->startOfDay()->gt(Carbon::parse($moveOutDate)->startOfDay())) {
            throw new BusinessException('Ngày dự kiến trả phòng không được nằm trong quá khứ.');
        }

        // 3. Cập nhật ngày thông báo
        $lease->update([
            'move_out_notice_date' => $moveOutDate,
        ]);

        return $lease;
    }
}
