<?php

declare(strict_types=1);

namespace App\Enums\Incident;

enum IncidentCategory: string
{
    case Electrical = 'electrical';
    case Water = 'water';
    case Furniture = 'furniture';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electrical => 'Điện',
            self::Water => 'Nước',
            self::Furniture => 'Nội thất',
            self::Security => 'An ninh',
            self::Other => 'Khác',
        };
    }
}