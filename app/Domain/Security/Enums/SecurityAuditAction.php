<?php

namespace App\Domain\Security\Enums;

enum SecurityAuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case PinOverride = 'pin_override';
    case FailedLogin = 'failed_login';
    case SettingsChange = 'settings_change';
    case RemoteWipe = 'remote_wipe';

    public function label(): string
    {
        return match ($this) {
            self::Login => __('security.audit_action_login'),
            self::Logout => __('security.audit_action_logout'),
            self::PinOverride => __('security.audit_action_pin_override'),
            self::FailedLogin => __('security.audit_action_failed_login'),
            self::SettingsChange => __('security.audit_action_settings_change'),
            self::RemoteWipe => __('security.audit_action_remote_wipe'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Login => 'success',
            self::Logout => 'gray',
            self::PinOverride => 'warning',
            self::FailedLogin => 'danger',
            self::SettingsChange => 'info',
            self::RemoteWipe => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Login => 'heroicon-o-arrow-right-on-rectangle',
            self::Logout => 'heroicon-o-arrow-left-on-rectangle',
            self::PinOverride => 'heroicon-o-key',
            self::FailedLogin => 'heroicon-o-x-circle',
            self::SettingsChange => 'heroicon-o-cog-6-tooth',
            self::RemoteWipe => 'heroicon-o-trash',
        };
    }
}
