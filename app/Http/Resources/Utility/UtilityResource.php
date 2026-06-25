<?php

declare(strict_types=1);

namespace App\Http\Resources\Utility;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'invoice_id' => $this->invoice_id,
            'type' => $this->type?->value ?? $this->type, // Xử lý Enum nếu có
            
            'previous_reading' => $this->previous_reading,
            'current_reading' => $this->current_reading,
            // Tự động tính số điện/nước tiêu thụ
            'usage' => max(0, $this->current_reading - $this->previous_reading),
            
            'reading_date' => $this->reading_date?->toDateString(),
            'meter_image' => $this->meter_image 
                ? asset('storage/' . ltrim($this->meter_image, '/')) 
                : null,
            
            'is_invoiced' => !is_null($this->invoice_id),
            'created_at' => $this->created_at?->toISOString(),

            /* Dữ liệu Flat cho Bảng hiển thị (Table) */
            'room_name' => $this->lease?->room?->name ?? '—',
            'property_name' => $this->lease?->room?->property?->name ?? '—',
            'property_id' => $this->lease?->room?->property_id ?? null,
            'tenant_name' => $this->lease?->tenant?->full_name ?? '—',
        ];
    }
}