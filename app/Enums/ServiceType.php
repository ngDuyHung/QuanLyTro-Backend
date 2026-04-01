<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceType: string
{
    case Electricity = 'electricity';
    case Water       = 'water';
    case Garbage     = 'garbage';
    case Internet    = 'internet';

    public function label(): string
    {
        return match($this) {
            self::Electricity => 'Điện',
            self::Water       => 'Nước',
            self::Garbage     => 'Rác',
            self::Internet    => 'Internet',
        };
    }

    public function unit(): string
    {
        return match($this) {
            self::Electricity => 'kWh',
            self::Water       => 'm³',
            self::Garbage     => 'tháng',
            self::Internet    => 'tháng',
        };
    }

    public function hasMeter(): bool
    {
        return in_array($this, [self::Electricity, self::Water], true);
    }
}
