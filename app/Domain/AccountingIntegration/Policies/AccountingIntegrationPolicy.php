<?php

namespace App\Domain\AccountingIntegration\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;

class AccountingIntegrationPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('accounting.view');
    }

    public function connect(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('accounting.connect');
    }

    public function export(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('accounting.export');
    }

    public function manageMappings(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('accounting.mappings');
    }
}
