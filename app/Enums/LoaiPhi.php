<?php

declare(strict_types=1);

namespace App\Enums;

enum LoaiPhi: string
{
    case Phong    = 'phong';
    case Dien     = 'dien';
    case Nuoc     = 'nuoc';
    case Rac      = 'rac';
    case Internet = 'internet';

    public function label(): string
    {
        return match($this) {
            self::Phong    => 'Tiền phòng',
            self::Dien     => 'Tiền điện',
            self::Nuoc     => 'Tiền nước',
            self::Rac      => 'Phí rác',
            self::Internet => 'Phí internet',
        };
    }

    public function isUtility(): bool
    {
        return in_array($this, [self::Dien, self::Nuoc], true);
    }

    public function isFixed(): bool
    {
        return in_array($this, [self::Rac, self::Internet], true);
    }
}
