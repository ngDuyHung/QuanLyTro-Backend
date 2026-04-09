<?php

declare(strict_types=1);

namespace App\Http\Resources\MeterReading;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeterReadingResources extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'lease_id'         => $this->lease_id,
            'invoice_id'       => $this->invoice_id,
            'type'             => $this->type?->value,
            'type_label'       => $this->type?->label(),
            'unit'             => $this->type?->unit(),
            'previous_reading' => $this->previous_reading,
            'current_reading'  => $this->current_reading,
            'consumption'      => $this->current_reading - $this->previous_reading,
            'meter_image'      => $this->meter_image ? asset('storage/' . $this->meter_image) : null,
            'reading_date'     => $this->reading_date?->toDateString(),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
