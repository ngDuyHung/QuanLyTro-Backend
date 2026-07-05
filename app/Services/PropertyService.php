<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class PropertyService
{
    public function firstOrCreateFromImport(int $userId, string $propertyCode, array $data): Property
    {
        $property = Property::firstOrCreate(
            ['user_id' => $userId, 'code' => $propertyCode],
            [
                'property_type'        => strtolower(trim((string)$data['property_type'])),
                'name'                 => trim((string)$data['property_name']),
                'status'               => strtolower(trim((string)$data['property_status'])) ?: 'active',
                'floors_count'         => (int)$data['property_floors_count'],
                'expected_rooms_count' => (int)$data['property_expected_rooms_count'],
                'manager_name'         => !empty($data['property_manager_name']) ? trim((string)$data['property_manager_name']) : null,
                'address'              => trim((string)$data['property_address']),
                'latitude'             => $data['property_latitude'] ?? null,
                'longitude'            => $data['property_longitude'] ?? null,
                'description'          => $data['property_description'] ?? null,
            ]
        );


        return $property;
    }
}
