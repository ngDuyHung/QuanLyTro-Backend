<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantNotificationService
{
    /**
     * Build query lấy danh sách thông báo hợp lệ cho user và hợp đồng hiện tại
     */
    private function buildTenantNotificationQuery(int $userId, ?int $leaseId = null): Builder
    {
        $tenant = Tenant::where('user_id', $userId)->first();

        if (!$tenant) {
            return Notification::query()->where('id', 0);
        }

        // 1. Tìm Hợp đồng đang active (theo ID truyền vào hoặc lấy mặc định cái đầu tiên)
        $query = Lease::where('status', 'active')
            ->where(function (Builder $q) use ($userId) {
                $q->whereHas('tenant', fn($t) => $t->where('user_id', $userId))
                  ->orWhereHas('members.tenant', fn($t) => $t->where('user_id', $userId));
            });

        if ($leaseId) {
            $query->where('id', $leaseId);
        }

        $lease = $query->first();

        // Nếu không tìm thấy hợp đồng nào, trả về query rỗng
        if (!$lease) {
            return Notification::query()->where('id', 0);
        }

        // 2. Suy ra chính xác Phòng, Khu nhà, Chủ trọ của hợp đồng này
        $roomId = $lease->room_id;
        $propertyId = Room::where('id', $roomId)->value('property_id');
        $landlordId = Property::where('id', $propertyId)->value('user_id');

        // 3. Query thông báo: Chỉ lấy thông báo liên quan đến Hợp đồng/Phòng đang chọn
        return Notification::with(['user:id,name'])
            ->where('status', 'published')
            ->where(function (Builder $query) use ($landlordId, $propertyId, $roomId) {
                // Gửi cho toàn bộ hệ thống của chủ trọ
                $query->where(function ($q) use ($landlordId) {
                    $q->where('target_type', 'all')
                        ->where('user_id', $landlordId);
                })
                // Hoặc gửi riêng cho khu nhà đang ở
                ->orWhere(function ($q) use ($propertyId) {
                    $q->where('target_type', 'property')
                        ->where('target_id', $propertyId);
                })
                // Hoặc gửi riêng cho phòng đang ở
                ->orWhere(function ($q) use ($roomId) {
                    $q->where('target_type', 'room')
                        ->where('target_id', $roomId);
                });
            });
    }

    public function getTenantNotifications(int $userId, int $perPage = 15, ?int $leaseId = null): LengthAwarePaginator
    {
        return $this->buildTenantNotificationQuery($userId, $leaseId)
            ->orderByDesc('is_pinned') // Ưu tiên ghim lên đầu
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function getNotificationDetail(int $id, int $userId, ?int $leaseId = null): Notification
    {
        $notification = $this->buildTenantNotificationQuery($userId, $leaseId)->find($id);

        if (!$notification) {
            throw new BusinessException('Thông báo không tồn tại hoặc bạn không có quyền xem.');
        }

        return $notification;
    }
}