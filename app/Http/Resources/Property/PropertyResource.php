<?php

declare(strict_types=1);

namespace App\Http\Resources\Property;

use App\Enums\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'property_type' => $this->property_type,
            'property_type_label' => $this->property_type->label(),
            'name'        => $this->name,
            'address'     => $this->address,
            'description' => $this->description,
            'total_rooms' => $this->rooms_count ?? 0,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
