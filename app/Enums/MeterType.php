<?php

declare(strict_types=1);

namespace App\Enums;

enum MeterType: string
{
    case Electricity = 'electricity';
    case Water       = 'water';

    public function label(): string
    {
        return match($this) {
            self::Electricity => 'Điện',
            self::Water       => 'Nước',
        };
    }

    public function unit(): string
    {
        return match($this) {
            self::Electricity => 'kWh',
            self::Water       => 'm³',
        };
    }

    public function toServiceType(): ServiceType
    {
        return match($this) {
            self::Electricity => ServiceType::Electricity,
            self::Water       => ServiceType::Water,
        };
    }
}
