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
            'id'            => $this->id,
            'property_id'   => $this->property_id,
            'name'          => $this->name,
            'area'          => $this->area ? (float) $this->area : null,
            'max_occupants' => $this->max_occupants,
            'current_price' => $this->current_price,
            'status'        => $this->status?->value,
            'status_label'  => $this->status?->label(),
            'description'   => $this->description,
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),

            // Chỉ trả về khi đã được load
            'property'      => $this->whenLoaded('property', fn () => [
                'id'      => $this->property->id,
                'name'    => $this->property->name,
                'address' => $this->property->address,
            ]),
        ];
    }
}
