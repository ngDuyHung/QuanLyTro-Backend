<?php

declare(strict_types=1);

namespace App\Enums;

enum RoomStatus: string
{
    case Available   = 'available';
    case Occupied    = 'occupied';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match($this) {
            self::Available   => 'Còn trống',
            self::Occupied    => 'Đang thuê',
            self::Maintenance => 'Đang sửa chữa',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::Available;
    }

    public function isRented(): bool
    {
        return $this === self::Occupied;
    }
}
