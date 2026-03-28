<?php

namespace App\Domain\Website\Enums;

enum ConsultationRequestStatus: string
{
    case New = 'new';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Scheduled => 'warning',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Closed => 'gray',
        };
    }
}
