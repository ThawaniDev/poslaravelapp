<?php

namespace App\Domain\Security\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SecurityPolicy
{
    public function viewAuditLog(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('security_audit_view');
    }

    public function manageDevices(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('security_device_manage');
    }

    public function managePolicies(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('security_policy_manage');
    }

    public function remoteWipe(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('security_remote_wipe');
    }
}
