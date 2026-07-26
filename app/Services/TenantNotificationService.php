<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomResident;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantNotificationService
{
    /**
     * Build query lấy danh sách thông báo hợp lệ cho user hiện tại
     */
    private function buildTenantNotificationQuery(int $userId): Builder
    {
        $tenant = Tenant::where('user_id', $userId)->first();

        if (!$tenant) {
            return Notification::query()->where('id', 0); // Trả về query rỗng nếu không phải khách
        }

        // 1. Lấy danh sách ID phòng mà khách đang ở (active)
        $roomIds = RoomResident::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->pluck('room_id')
            ->toArray();

        if (empty($roomIds)) {
            return Notification::query()->where('id', 0);
        }

        // 2. Suy ra Khu nhà và Chủ trọ
        $propertyIds = Room::whereIn('id', $roomIds)->pluck('property_id')->unique()->toArray();
        $landlordIds = Property::whereIn('id', $propertyIds)->pluck('user_id')->unique()->toArray();

        // 3. Query thông báo
        return Notification::with(['user:id,name']) // Kéo theo tên chủ trọ
            ->where('status', 'published') // Chỉ lấy thông báo đã phát hành
            ->where(function (Builder $query) use ($landlordIds, $propertyIds, $roomIds) {
                // Điều kiện 1: Gửi cho toàn bộ hệ thống của chủ trọ đó
                $query->where(function ($q) use ($landlordIds) {
                    $q->where('target_type', 'all')
                      ->whereIn('user_id', $landlordIds);
                })
                // Điều kiện 2: Gửi riêng cho khu nhà khách đang ở
                ->orWhere(function ($q) use ($propertyIds) {
                    $q->where('target_type', 'property')
                      ->whereIn('target_id', $propertyIds);
                })
                // Điều kiện 3: Gửi riêng cho phòng khách đang ở
                ->orWhere(function ($q) use ($roomIds) {
                    $q->where('target_type', 'room')
                      ->whereIn('target_id', $roomIds);
                });
            });
    }

    public function getTenantNotifications(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildTenantNotificationQuery($userId)
            ->orderByDesc('is_pinned') // Ưu tiên ghim lên đầu
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function getNotificationDetail(int $id, int $userId): Notification
    {
        $notification = $this->buildTenantNotificationQuery($userId)->find($id);

        if (!$notification) {
            throw new BusinessException('Thông báo không tồn tại hoặc bạn không có quyền xem.');
        }

        return $notification;
    }
}