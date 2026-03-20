<?php

namespace App\Domain\IndustryElectronics\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;

class IndustryElectronicsPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('electronics.view');
    }

    public function manageImei(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('electronics.imei');
    }

    public function manageRepairs(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('electronics.repairs');
    }

    public function manageTradeIn(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('electronics.trade_in');
    }
}
