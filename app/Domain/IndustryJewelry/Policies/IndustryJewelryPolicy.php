<?php

namespace App\Domain\IndustryJewelry\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;

class IndustryJewelryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('jewelry.view');
    }

    public function manage(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('jewelry.manage');
    }

    public function manageBuyback(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('jewelry.buyback');
    }

    public function manageRates(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('jewelry.rates');
    }
}
