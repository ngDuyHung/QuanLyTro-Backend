<?php

declare(strict_types=1);

namespace App\Enums;

enum LoaiChiSo: string
{
    case Dien = 'dien';
    case Nuoc = 'nuoc';

    public function label(): string
    {
        return match($this) {
            self::Dien => 'Điện',
            self::Nuoc => 'Nước',
        };
    }

    public function donVi(): string
    {
        return match($this) {
            self::Dien => 'kWh',
            self::Nuoc => 'm³',
        };
    }

    public function toLoaiDichVu(): LoaiDichVu
    {
        return match($this) {
            self::Dien => LoaiDichVu::Dien,
            self::Nuoc => LoaiDichVu::Nuoc,
        };
    }
}
