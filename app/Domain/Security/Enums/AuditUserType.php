<?php

namespace App\Domain\Security\Enums;

enum AuditUserType: string
{
    case Staff = 'staff';
    case Owner = 'owner';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Staff => __('security.user_type_staff'),
            self::Owner => __('security.user_type_owner'),
            self::System => __('security.user_type_system'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Staff => 'primary',
            self::Owner => 'warning',
            self::System => 'gray',
        };
    }
}
