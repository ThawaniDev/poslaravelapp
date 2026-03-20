<?php

namespace App\Domain\IndustryBakery\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;

class IndustryBakeryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('bakery.view');
    }

    public function manageRecipes(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('bakery.recipes');
    }

    public function manageOrders(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('bakery.orders');
    }

    public function manageProduction(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('bakery.production');
    }
}
