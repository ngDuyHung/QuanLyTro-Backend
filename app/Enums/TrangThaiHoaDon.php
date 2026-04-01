<?php

declare(strict_types=1);

namespace App\Enums;

enum TrangThaiHoaDon: string
{
    case ChuaThanhToan      = 'chua_thanh_toan';
    case DaThanhToan        = 'da_thanh_toan';
    case ThanhToanMotPhan   = 'thanh_toan_mot_phan';

    public function label(): string
    {
        return match($this) {
            self::ChuaThanhToan    => 'Chưa thanh toán',
            self::DaThanhToan      => 'Đã thanh toán',
            self::ThanhToanMotPhan => 'Thanh toán một phần',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::DaThanhToan;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::ChuaThanhToan, self::ThanhToanMotPhan], true);
    }
}
