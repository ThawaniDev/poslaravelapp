<?php

namespace App\Domain\ZatcaCompliance\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ZatcaCompliancePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('zatca_view');
    }

    public function submit(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('zatca_submit');
    }

    public function enroll(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('zatca_manage');
    }
}
