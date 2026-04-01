<?php

declare(strict_types=1);

namespace App\Enums;

enum KieuMienPhi: string
{
    case Khong      = 'khong';
    case TheoPhong  = 'theo_phong';
    case TheoNguoi  = 'theo_nguoi';

    public function label(): string
    {
        return match($this) {
            self::Khong     => 'Không miễn phí',
            self::TheoPhong => 'Miễn phí theo phòng',
            self::TheoNguoi => 'Miễn phí theo người',
        };
    }

    public function hasFreeUnits(): bool
    {
        return $this !== self::Khong;
    }
}
