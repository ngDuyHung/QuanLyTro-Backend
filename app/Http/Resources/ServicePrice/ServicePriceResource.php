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
            'id' => $this->id,
            'property_id' => $this->property_id,
            'service_type' => $this->service_type,
            'unit_price' => $this->unit_price,
            'free_units' => $this->free_units,
            'free_unit_type' => $this->free_unit_type,
            'effective_date' => $this->effective_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
