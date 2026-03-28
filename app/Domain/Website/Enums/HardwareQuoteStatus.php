<?php

namespace App\Domain\Website\Enums;

enum HardwareQuoteStatus: string
{
    case New = 'new';
    case Quoted = 'quoted';
    case Negotiating = 'negotiating';
    case Ordered = 'ordered';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Quoted => 'Quoted',
            self::Negotiating => 'Negotiating',
            self::Ordered => 'Ordered',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Quoted => 'warning',
            self::Negotiating => 'primary',
            self::Ordered => 'success',
            self::Closed => 'gray',
        };
    }
}
