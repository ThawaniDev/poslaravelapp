<?php

namespace App\Domain\Website\Enums;

enum ContactSubmissionStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Converted => 'Converted',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::Qualified => 'primary',
            self::Converted => 'success',
            self::Closed => 'gray',
        };
    }
}
