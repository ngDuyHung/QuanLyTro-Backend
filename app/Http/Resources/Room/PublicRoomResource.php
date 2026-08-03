<?php

declare(strict_types=1);

namespace App\Http\Resources\Room;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'floor_number' => $this->floor_number,
            'area' => $this->area ? (float) $this->area : null,
            'max_occupants' => $this->max_occupants,
            'current_price' => $this->current_price,
            'deposit_amount' => $this->deposit_amount,
            'description' => $this->description,
            'amenities' => $this->amenities ?? [],
            'created_at' => $this->created_at?->toISOString(),

            // Tính số ngày đã trôi qua để hiển thị nhãn "Mới đăng" (VD: <= 7 ngày)
            'is_new' => $this->created_at ? $this->created_at->diffInDays(now()) <= 7 : false,

            // Thông tin khu nhà (chỉ lấy tên và địa chỉ)
            'property' => $this->whenLoaded('property', fn() => [
                'id' => $this->property->id,
                'name' => $this->property->name,
                'address' => $this->property->address,
                'service_prices' => $this->property->relationLoaded('servicePrices') ? $this->property->servicePrices : [],
            ]),

            // Thông tin Chủ trọ (lấy từ bảng users thông qua property)
            'landlord' => $this->whenLoaded('property', function () {
                $user = $this->property->user ?? null;
                if (!$user) return null;

                return [
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'zalo_id' => $user->zalo_id, // Nếu cần link tới Zalo
                ];
            }),

            // Danh sách ảnh
            'images' => $this->whenLoaded(
                'images',
                fn() =>
                $this->images->map(fn($image) => [
                    'id' => $image->id,
                    'image_url' => $image->image_path
                        ? asset('storage/' . ltrim($image->image_path, '/'))
                        : null,
                    'is_cover' => (bool) $image->is_cover,
                ])->values()
            ),
        ];
    }
}
