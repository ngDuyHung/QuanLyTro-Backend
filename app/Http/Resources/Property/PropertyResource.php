<?php

declare(strict_types=1);

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalRooms = (int) ($this->rooms_count ?? 0);
        $availableRooms = (int) ($this->available_rooms_count ?? 0);
        $occupiedRooms = (int) ($this->occupied_rooms_count ?? 0);
        $maintenanceRooms = (int) ($this->maintenance_rooms_count ?? 0);

        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100)
            : 0;

        $availableRate = $totalRooms > 0
            ? round(($availableRooms / $totalRooms) * 100)
            : 0;

        $maintenanceRate = $totalRooms > 0
            ? round(($maintenanceRooms / $totalRooms) * 100)
            : 0;

        return [
            'id' => $this->id,

            'property_type' => $this->property_type?->value,
            'property_type_label' => $this->property_type?->label(),

            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,

            'floors_count' => $this->floors_count,
            'expected_rooms_count' => $this->expected_rooms_count,

            'manager_name' => $this->manager_name,

            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'cover_image_path' => $this->cover_image_path,
            'cover_image_url' => $this->cover_image_path
                ? asset('storage/' . ltrim($this->cover_image_path, '/'))
                : null,

            'description' => $this->description,

            'total_rooms' => $totalRooms,
            'available_rooms' => $availableRooms,
            'occupied_rooms' => $occupiedRooms,
            'maintenance_rooms' => $maintenanceRooms,

            'occupancy_rate' => $occupancyRate,
            'available_rate' => $availableRate,
            'maintenance_rate' => $maintenanceRate,

            'remaining_rooms_to_create' => max(
                ((int) ($this->expected_rooms_count ?? 0)) - $totalRooms,
                0
            ),

            'room_stats' => [
                'total' => $totalRooms,
                'available' => $availableRooms,
                'occupied' => $occupiedRooms,
                'maintenance' => $maintenanceRooms,
                'occupancy_rate' => $occupancyRate,
                'available_rate' => $availableRate,
                'maintenance_rate' => $maintenanceRate,
            ],

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}