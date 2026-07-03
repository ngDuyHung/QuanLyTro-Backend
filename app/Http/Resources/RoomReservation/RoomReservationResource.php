<?php

declare(strict_types=1);

namespace App\Http\Resources\RoomReservation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'tenant_name' => $this->tenant_name,
            'tenant_phone' => $this->tenant_phone,
            'deposit_amount' => $this->deposit_amount,
            'expected_move_in_date' => $this->expected_move_in_date?->toDateString(),
            'status' => $this->status,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            
            'room' => $this->whenLoaded('room', fn() => [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'property_id' => $this->room->property_id,
            ]),
        ];
    }
}