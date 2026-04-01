<?php

declare(strict_types=1);

namespace App\Enums;

enum HinhThucThanhToan: string
{
    case TienMat      = 'tien_mat';
    case ChuyenKhoan  = 'chuyen_khoan';

    public function label(): string
    {
        return match($this) {
            self::TienMat     => 'Tiền mặt',
            self::ChuyenKhoan => 'Chuyển khoản',
        };
    }

    public function requiresBankAccount(): bool
    {
        return $this === self::ChuyenKhoan;
    }
}
