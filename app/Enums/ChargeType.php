<?php

declare(strict_types=1);

namespace App\Enums;

enum ChargeType: string
{
    case Room        = 'room';
    case Electricity = 'electricity';
    case Water       = 'water';
    case Garbage     = 'garbage';
    case Internet    = 'internet';

    public function label(): string
    {
        return match($this) {
            self::Room        => 'Tiền phòng',
            self::Electricity => 'Tiền điện',
            self::Water       => 'Tiền nước',
            self::Garbage     => 'Phí rác',
            self::Internet    => 'Phí internet',
        };
    }

    public function isUtility(): bool
    {
        return in_array($this, [self::Electricity, self::Water], true);
    }

    public function isFixed(): bool
    {
        return in_array($this, [self::Garbage, self::Internet], true);
    }
}
