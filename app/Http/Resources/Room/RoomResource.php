<?php

declare(strict_types=1);

namespace App\Http\Resources\Room;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'property_id' => $this->property_id,

            'name' => $this->name,

            'floor_number' => $this->floor_number,

            'area' => $this->area ? (float) $this->area : null,

            'max_occupants' => $this->max_occupants,

            'current_price' => $this->current_price,

            'billing_day' => $this->billing_day,

            'allow_shared' => (bool) $this->allow_shared,

            'is_public' => (bool) $this->is_public,

            'status' => $this->status?->value,

            'status_label' => $this->status?->label(),

            'description' => $this->description,

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),

            'property' => $this->whenLoaded('property', fn() => [
                'id' => $this->property->id,
                'name' => $this->property->name,
                'address' => $this->property->address,
            ]),

            'images' => $this->whenLoaded(
                'images',
                fn() =>
                $this->images->map(fn($image) => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => $image->image_path
                        ? asset('storage/' . ltrim($image->image_path, '/'))
                        : null,
                    'is_cover' => (bool) $image->is_cover,
                    'sort_order' => $image->sort_order,
                ])->values()
            ),
        ];
    }
}
