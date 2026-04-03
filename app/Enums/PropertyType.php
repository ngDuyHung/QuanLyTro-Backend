<?php

declare(strict_types=1);

namespace App\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment'; // Chung cư
    case House = 'house';         // Nhà nguyên căn
    case BoardingHouse = 'boarding_house'; // Nhà trọ

    public function label(): string
    {
        return match ($this) {
            self::Apartment => 'Chung cư',
            self::House => 'Nhà nguyên căn',
            self::BoardingHouse => 'Nhà trọ',
        };
    }
}
