<?php

namespace App\Domain\Security\Enums;

enum RoleAuditAction: string
{
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';

    public function label(): string
    {
        return match ($this) {
            self::RoleCreated => __('security.role_audit_created'),
            self::RoleUpdated => __('security.role_audit_updated'),
            self::PermissionGranted => __('security.role_audit_permission_granted'),
            self::PermissionRevoked => __('security.role_audit_permission_revoked'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RoleCreated => 'success',
            self::RoleUpdated => 'info',
            self::PermissionGranted => 'warning',
            self::PermissionRevoked => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RoleCreated => 'heroicon-o-user-plus',
            self::RoleUpdated => 'heroicon-o-pencil-square',
            self::PermissionGranted => 'heroicon-o-shield-check',
            self::PermissionRevoked => 'heroicon-o-shield-exclamation',
        };
    }
}
