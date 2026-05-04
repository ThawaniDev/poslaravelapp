<?php

namespace App\Domain\Security\Enums;

enum AuditResourceType: string
{
    case Order = 'order';
    case Product = 'product';
    case StaffUser = 'staff_user';
    case Settings = 'settings';
    case Terminal = 'terminal';

    public function label(): string
    {
        return match ($this) {
            self::Order => __('security.resource_order'),
            self::Product => __('security.resource_product'),
            self::StaffUser => __('security.resource_staff_user'),
            self::Settings => __('security.resource_settings'),
            self::Terminal => __('security.resource_terminal'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Order => 'primary',
            self::Product => 'success',
            self::StaffUser => 'warning',
            self::Settings => 'info',
            self::Terminal => 'gray',
        };
    }
}
