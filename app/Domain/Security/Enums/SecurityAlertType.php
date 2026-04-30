<?php

namespace App\Domain\Security\Enums;

enum SecurityAlertType: string
{
    case BruteForce = 'brute_force';
    case BulkExport = 'bulk_export';
    case UnusualIp = 'unusual_ip';
    case PermissionEscalation = 'permission_escalation';
    case AfterHoursAccess = 'after_hours_access';
    case AppCrashLoop = 'app_crash_loop';

    public function label(): string
    {
        return match ($this) {
            self::BruteForce => __('security.alert_type_brute_force'),
            self::BulkExport => __('security.alert_type_bulk_export'),
            self::UnusualIp => __('security.alert_type_unusual_ip'),
            self::PermissionEscalation => __('security.alert_type_permission_escalation'),
            self::AfterHoursAccess => __('security.alert_type_after_hours'),
            self::AppCrashLoop => __('security.alert_type_app_crash_loop'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BruteForce => 'danger',
            self::BulkExport => 'warning',
            self::UnusualIp => 'info',
            self::PermissionEscalation => 'danger',
            self::AfterHoursAccess => 'warning',
            self::AppCrashLoop => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BruteForce => 'heroicon-o-shield-exclamation',
            self::BulkExport => 'heroicon-o-arrow-down-tray',
            self::UnusualIp => 'heroicon-o-globe-alt',
            self::PermissionEscalation => 'heroicon-o-arrow-trending-up',
            self::AfterHoursAccess => 'heroicon-o-clock',
            self::AppCrashLoop => 'heroicon-o-x-circle',
        };
    }
}
