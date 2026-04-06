<?php

declare(strict_types=1);

namespace App\Http\Resources\LeaseMember;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'lease_id'     => $this->lease_id,
            'tenant_id'    => $this->tenant_id,
            'relationship' => $this->relationship?->value,
            'relationship_label' => $this->relationship?->label(),
            'note'         => $this->note,
            'move_in_date' => $this->move_in_date?->toDateString(),
            'move_out_date'=> $this->move_out_date?->toDateString(),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),

            // Thông tin khách thuê — chỉ hiện khi đã được load
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id'              => $this->tenant->id,
                'full_name'       => $this->tenant->full_name,
                'phone'           => $this->tenant->phone,
                'email'           => $this->tenant->email,
                'id_card_number'  => $this->tenant->id_card_number,
            ]),
        ];
    }
}
