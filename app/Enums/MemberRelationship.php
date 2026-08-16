<?php

declare(strict_types=1);

namespace App\Enums;

enum MemberRelationship: string
{
    case Spouse  = 'spouse';
    case Child   = 'child';
    case Parent  = 'parent';
    case Sibling = 'sibling';
    case Friend  = 'friend';
    case Other   = 'other';
    case Roommate = 'roommate';

    public function label(): string
    {
        return match($this) {
            self::Spouse  => 'Vợ/Chồng',
            self::Child   => 'Con',
            self::Parent  => 'Cha/Mẹ',
            self::Sibling => 'Anh/Chị/Em',
            self::Friend  => 'Bạn bè',
            self::Other   => 'Khác',
            self::Roommate => 'Người ở ghép'
        };
    }

    public function isFamily(): bool
    {
        return in_array($this, [self::Spouse, self::Child, self::Parent, self::Sibling], true);
    }
}
