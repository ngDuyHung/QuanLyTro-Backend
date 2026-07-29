<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentResidence = $this->whenLoaded('currentResidence');

        return [
            'id' => $this->id,

            'user_id' => $this->user_id,

            'full_name' => $this->full_name,
            'name' => $this->full_name,

            'email' => $this->email,
            'phone' => $this->phone,

            'id_card_number' => $this->id_card_number,
            'cccd' => $this->id_card_number,

            'id_card_front_image' => $this->id_card_front_image
                ? asset('storage/' . ltrim($this->id_card_front_image, '/'))
                : null,

            'id_card_back_image' => $this->id_card_back_image
                ? asset('storage/' . ltrim($this->id_card_back_image, '/'))
                : null,

            'current_residence' => $this->whenLoaded('currentResidence', function () {
                if (!$this->currentResidence) {
                    return null;
                }

                return [
                    'id' => $this->currentResidence->id,
                    'room_id' => $this->currentResidence->room_id,
                    'lease_id' => $this->currentResidence->lease_id,
                    'role' => $this->currentResidence->role,
                    'status' => $this->currentResidence->status,
                    'move_in_date' => $this->currentResidence->move_in_date?->toDateString(),
                    'move_out_date' => $this->currentResidence->move_out_date?->toDateString(),
                    'note' => $this->currentResidence->note,

                    'room' => $this->currentResidence->room ? [
                        'id' => $this->currentResidence->room->id,
                        'name' => $this->currentResidence->room->name,
                        'property_id' => $this->currentResidence->room->property_id,
                        'property' => $this->currentResidence->room->property ? [
                            'id' => $this->currentResidence->room->property->id,
                            'name' => $this->currentResidence->room->property->name,
                        ] : null,
                    ] : null,
                ];
            }),

            /*
            |--------------------------------------------------------------------------
            | Field phẳng để FE TenantTable dùng dễ hơn
            |--------------------------------------------------------------------------
            */
            'room' => $this->currentResidence?->room?->name ?? 'Chưa gắn phòng',
            'property' => $this->currentResidence?->room?->property?->name,
            'room_id' => $this->currentResidence?->room_id,
            'property_id' => $this->currentResidence?->room?->property_id,
            'role' => $this->currentResidence?->role ?? 'member',
            'status' => $this->currentResidence?->status ?? 'left',
            'move_in_date' => $this->currentResidence?->move_in_date?->toDateString(),
            'move_out_date' => $this->currentResidence?->move_out_date?->toDateString(),

            'residence_history' => $this->whenLoaded('roomResidents', function () {
                return $this->roomResidents->map(fn ($item) => [
                    'id' => $item->id,
                    'room_id' => $item->room_id,
                    'room_name' => $item->room?->name,
                    'property_name' => $item->room?->property?->name,
                    'lease_id' => $item->lease_id,
                    'role' => $item->role,
                    'status' => $item->status,
                    'move_in_date' => $item->move_in_date?->toDateString(),
                    'move_out_date' => $item->move_out_date?->toDateString(),
                    'note' => $item->note,
                ])->values();
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}