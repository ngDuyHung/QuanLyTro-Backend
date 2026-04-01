<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin     = 'admin';
    case ChuTro    = 'chu_tro';
    case NguoiThue = 'nguoi_thue';

    public function label(): string
    {
        return match($this) {
            self::Admin     => 'Quản trị viên',
            self::ChuTro    => 'Chủ trọ',
            self::NguoiThue => 'Người thuê',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isChuTro(): bool
    {
        return $this === self::ChuTro;
    }

    public function isNguoiThue(): bool
    {
        return $this === self::NguoiThue;
    }

    public function canManageSystem(): bool
    {
        return $this === self::Admin;
    }

    public function canManageProperties(): bool
    {
        return in_array($this, [self::Admin, self::ChuTro], true);
    }
}
