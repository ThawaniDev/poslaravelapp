<?php

namespace App\Domain\Security\Enums;

enum RoleAuditAction: string
{
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';
}
