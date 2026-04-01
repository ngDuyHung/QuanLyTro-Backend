<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash         = 'cash';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match($this) {
            self::Cash         => 'Tiền mặt',
            self::BankTransfer => 'Chuyển khoản',
        };
    }

    public function requiresBankAccount(): bool
    {
        return $this === self::BankTransfer;
    }
}
