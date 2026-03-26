<?php

namespace App\Domain\Support\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('support.status_open'),
            self::InProgress => __('support.status_in_progress'),
            self::Resolved => __('support.status_resolved'),
            self::Closed => __('support.status_closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-o-inbox',
            self::InProgress => 'heroicon-o-arrow-path',
            self::Resolved => 'heroicon-o-check-circle',
            self::Closed => 'heroicon-o-lock-closed',
        };
    }
}
