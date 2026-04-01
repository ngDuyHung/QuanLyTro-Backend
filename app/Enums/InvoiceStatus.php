<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid        = 'unpaid';
    case Paid          = 'paid';
    case PartiallyPaid = 'partially_paid';

    public function label(): string
    {
        return match($this) {
            self::Unpaid        => 'Chưa thanh toán',
            self::Paid          => 'Đã thanh toán',
            self::PartiallyPaid => 'Thanh toán một phần',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Unpaid, self::PartiallyPaid], true);
    }
}
