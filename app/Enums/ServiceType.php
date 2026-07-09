<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceType: string
{
    case Electricity = 'electricity';
    case Water       = 'water';
    case Garbage     = 'garbage';
    case Internet    = 'internet';

    case Parking     = 'parking';
    case Cleaning    = 'cleaning';
    case Elevator    = 'elevator';
    case Management  = 'management';
    case Other       = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Điện',
            self::Water       => 'Nước',
            self::Garbage     => 'Rác',
            self::Internet    => 'Internet',
            self::Parking     => 'Giữ xe',
            self::Cleaning    => 'Vệ sinh',
            self::Elevator    => 'Thang máy',
            self::Management  => 'Phí quản lý',
            self::Other       => 'Khác',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Electricity => 'kWh',
            self::Water       => 'm³',
            self::Garbage, self::Internet, self::Cleaning, self::Elevator, self::Management => 'tháng',
            self::Parking     => 'chiếc/tháng',
            self::Other       => 'lần/tháng',
        };
    }

    public function hasMeter(): bool
    {
        return in_array($this, [self::Electricity, self::Water], true);
    }
}
