<?php

declare(strict_types=1);

namespace App\Enums;

enum TrangThaiBanGhiThue: string
{
    case DangThue = 'dang_thue';
    case DaTra    = 'da_tra';

    public function label(): string
    {
        return match($this) {
            self::DangThue => 'Đang thuê',
            self::DaTra    => 'Đã trả phòng',
        };
    }

    public function isActive(): bool
    {
        return $this === self::DangThue;
    }
}
