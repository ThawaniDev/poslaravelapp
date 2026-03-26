<?php

namespace App\Domain\Security\Enums;

enum SecurityAlertStatus: string
{
    case New = 'new';
    case Investigating = 'investigating';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => __('security.alert_status_new'),
            self::Investigating => __('security.alert_status_investigating'),
            self::Resolved => __('security.alert_status_resolved'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Investigating => 'warning',
            self::Resolved => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'heroicon-o-bell-alert',
            self::Investigating => 'heroicon-o-magnifying-glass',
            self::Resolved => 'heroicon-o-check-circle',
        };
    }
}
