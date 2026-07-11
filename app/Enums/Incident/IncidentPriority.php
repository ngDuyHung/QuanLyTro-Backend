<?php

declare(strict_types=1);

namespace App\Enums\Incident;

enum IncidentPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Normal => 'Bình thường',
            self::High => 'Cao',
            self::Emergency => 'Khẩn cấp',
        };
    }
}