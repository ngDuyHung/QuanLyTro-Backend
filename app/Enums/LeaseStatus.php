<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Ended  = 'ended';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Đang thuê',
            self::Ended  => 'Đã trả phòng',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
