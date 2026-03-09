<?php

namespace App\Domain\Security\Enums;

enum AuditUserType: string
{
    case Staff = 'staff';
    case Owner = 'owner';
    case System = 'system';
}
