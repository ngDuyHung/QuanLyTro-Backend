<?php

declare(strict_types=1);

namespace App\Enums;

enum FreeUnitType: string
{
    case None      = 'none';
    case PerRoom   = 'per_room';
    case PerPerson = 'per_person';

    public function label(): string
    {
        return match($this) {
            self::None      => 'Không miễn phí',
            self::PerRoom   => 'Miễn phí theo phòng',
            self::PerPerson => 'Miễn phí theo người',
        };
    }

    public function hasFreeUnits(): bool
    {
        return $this !== self::None;
    }
}
