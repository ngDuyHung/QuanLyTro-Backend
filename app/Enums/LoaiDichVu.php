<?php

declare(strict_types=1);

namespace App\Enums;

enum LoaiDichVu: string
{
    case Dien     = 'dien';
    case Nuoc     = 'nuoc';
    case Rac      = 'rac';
    case Internet = 'internet';

    public function label(): string
    {
        return match($this) {
            self::Dien     => 'Điện',
            self::Nuoc     => 'Nước',
            self::Rac      => 'Rác',
            self::Internet => 'Internet',
        };
    }

    public function donViTinh(): string
    {
        return match($this) {
            self::Dien     => 'kWh',
            self::Nuoc     => 'm³',
            self::Rac      => 'tháng',
            self::Internet => 'tháng',
        };
    }

    public function hasMeter(): bool
    {
        return in_array($this, [self::Dien, self::Nuoc], true);
    }
}
