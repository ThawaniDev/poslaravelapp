<?php

namespace App\Domain\Security\Enums;

enum AuditResourceType: string
{
    case Order = 'order';
    case Product = 'product';
    case StaffUser = 'staff_user';
    case Settings = 'settings';
}
