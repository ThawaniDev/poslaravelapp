<?php

namespace App\Domain\Website\Enums;

enum PartnershipApplicationStatus: string
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Reviewing => 'Reviewing',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Reviewing => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Closed => 'gray',
        };
    }
}
