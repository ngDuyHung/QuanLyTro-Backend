<?php

declare(strict_types=1);

namespace App\Enums\Incident;

enum IncidentPayer: string
{
    case Landlord = 'landlord';
    case Tenant = 'tenant';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Landlord => 'Chủ trọ',
            self::Tenant => 'Khách thuê',
            self::None => 'Không mất phí',
        };
    }
}