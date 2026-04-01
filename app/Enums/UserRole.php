<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin    = 'admin';
    case Landlord = 'landlord';
    case Tenant   = 'tenant';

    public function label(): string
    {
        return match($this) {
            self::Admin    => 'Quản trị viên',
            self::Landlord => 'Chủ trọ',
            self::Tenant   => 'Người thuê',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isLandlord(): bool
    {
        return $this === self::Landlord;
    }

    public function isTenant(): bool
    {
        return $this === self::Tenant;
    }

    public function canManageSystem(): bool
    {
        return $this === self::Admin;
    }
}
