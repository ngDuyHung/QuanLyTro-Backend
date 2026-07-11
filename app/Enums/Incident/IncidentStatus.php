<?php

declare(strict_types=1);

namespace App\Enums\Incident;

enum IncidentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ tiếp nhận',
            self::Processing => 'Đang xử lý',
            self::Resolved => 'Đã giải quyết',
            self::Cancelled => 'Đã hủy',
        };
    }
}