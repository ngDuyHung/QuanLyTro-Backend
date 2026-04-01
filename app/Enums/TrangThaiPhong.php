<?php

declare(strict_types=1);

namespace App\Enums;

enum TrangThaiPhong: string
{
    case Trong    = 'trong';
    case DangThue = 'dang_thue';
    case SuaChua  = 'sua_chua';

    public function label(): string
    {
        return match($this) {
            self::Trong    => 'Còn trống',
            self::DangThue => 'Đang thuê',
            self::SuaChua  => 'Đang sửa chữa',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::Trong;
    }

    public function isRented(): bool
    {
        return $this === self::DangThue;
    }
}
