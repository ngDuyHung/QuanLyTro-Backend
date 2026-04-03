<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'user_id'             => $this->user_id,
            'full_name'           => $this->full_name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'id_card_number'      => $this->id_card_number,
            'id_card_front_image' => $this->id_card_front_image
                ? asset('storage/' . $this->id_card_front_image)
                : null,
            'id_card_back_image'  => $this->id_card_back_image
                ? asset('storage/' . $this->id_card_back_image)
                : null,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),

            // Relationship — chỉ hiện khi đã được load
            'leases' => $this->whenLoaded('leases', function () {
                return $this->leases->map(fn ($lease) => [
                    'id'           => $lease->id,
                    'status'       => $lease->status?->value,
                    'status_label' => $lease->status?->label(),
                    'start_date'   => $lease->start_date?->toDateString(),
                    'end_date'     => $lease->end_date?->toDateString(),
                    'room'         => $lease->relationLoaded('room') ? [
                        'id'       => $lease->room->id,
                        'name'     => $lease->room->name,
                        'property' => $lease->room->relationLoaded('property') ? [
                            'id'   => $lease->room->property->id,
                            'name' => $lease->room->property->name,
                        ] : null,
                    ] : null,
                ]);
            }),
        ];
    }
}


