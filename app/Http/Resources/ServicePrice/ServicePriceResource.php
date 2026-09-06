<?php

declare(strict_types=1);

namespace App\Http\Resources\ServicePrice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePriceResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'property_id'    => $this->property_id,
            'service_type'   => $this->service_type?->value,
            'service_type_label' => $this->service_type?->label(),
            'unit_price'     => $this->unit_price,
            'base_price'     => $this->base_price,
            'free_units'     => $this->free_units,
            'free_unit_type' => $this->free_unit_type?->value,
            'free_unit_type_label' => $this->free_unit_type?->label(),
            'effective_date' => $this->effective_date?->toDateString(),
            'expiry_date'    => $this->expiry_date?->toDateString(),
            'note'           => $this->note,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),

            //Lịch sủ thay đổi giá (chỉ hiện khi đã được load)
            'price_histories' => $this->whenLoaded('priceHistories', function () {
                return $this->priceHistories->map(fn($history) => [
                    'id' => $history->id,
                    'user_id' => $history->user_id,
                    'old_price' => $history->old_price,
                    'new_price' => $history->new_price,
                    'changed_date' => $history->changed_date?->toDateString(),
                    'reason' => $history->reason,
                    'created_at' => $history->created_at?->toISOString(),
                ]);
            }),
        ];
    }
}
