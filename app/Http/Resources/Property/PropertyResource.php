<?php

declare(strict_types=1);

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'property_type' => $this->property_type?->value,
            'property_type_label' => $this->property_type?->label(),

            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,

            'floors_count' => $this->floors_count,
            'expected_rooms_count' => $this->expected_rooms_count,

            'manager_name' => $this->manager_name,

            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'cover_image_path' => $this->cover_image_path,
            'description' => $this->description,

            'total_rooms' => $this->rooms_count ?? 0,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}