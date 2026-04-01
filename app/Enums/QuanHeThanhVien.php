<?php

declare(strict_types=1);

namespace App\Enums;

enum QuanHeThanhVien: string
{
    case VoChong    = 'vo_chong';
    case Con        = 'con';
    case ChaME      = 'cha_me';
    case AnhChiEm   = 'anh_chi_em';
    case BanBe      = 'ban_be';
    case Khac       = 'khac';

    public function label(): string
    {
        return match($this) {
            self::VoChong  => 'Vợ/Chồng',
            self::Con      => 'Con',
            self::ChaME    => 'Cha/Mẹ',
            self::AnhChiEm => 'Anh/Chị/Em',
            self::BanBe    => 'Bạn bè',
            self::Khac     => 'Khác',
        };
    }

    public function isFamily(): bool
    {
        return in_array($this, [self::VoChong, self::Con, self::ChaME, self::AnhChiEm], true);
    }
}
