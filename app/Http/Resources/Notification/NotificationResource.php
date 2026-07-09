<?php

declare(strict_types=1);

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Xử lý chuỗi hiển thị đối tượng mục tiêu
        $targetTypeLabel = match ($this->target_type) {
            'all' => 'Toàn hệ thống',
            'property' => $this->targetProperty
                ? 'Khu nhà: ' . $this->targetProperty->name
                : 'Khu nhà (Đã xóa)',
            'room' => $this->targetRoom
                ? 'Phòng ' . $this->targetRoom->name . ' - ' . ($this->targetRoom->property->name ?? 'Không rõ')
                : 'Phòng (Đã xóa)',
            default => 'Khác',
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,

            'type' => $this->type,
            'type_label' => match ($this->type) {
                'info' => 'Thông tin',
                'warning' => 'Cảnh báo',
                'billing' => 'Tài chính',
                default => 'Khác',
            },

            'target_type' => $this->target_type,
            'target_type_label' => $targetTypeLabel, // Trả về biến đã xử lý ở trên
            'target_id' => $this->target_id,

            'is_pinned' => $this->is_pinned,

            'status' => $this->status,
            'status_label' => match ($this->status) {
                'draft' => 'Bản nháp',
                'published' => 'Đã đăng',
                default => 'Khác',
            },

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
