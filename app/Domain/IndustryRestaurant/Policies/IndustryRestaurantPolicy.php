<?php

namespace App\Domain\IndustryRestaurant\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\IndustryRestaurant\Models\RestaurantTable;
use Illuminate\Contracts\Auth\Authenticatable;

class IndustryRestaurantPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('restaurant.view');
    }

    public function manageTables(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('restaurant.tables');
    }

    public function manageReservations(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('restaurant.reservations');
    }

    public function manageKitchen(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('restaurant.kitchen');
    }

    public function manageTabs(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('restaurant.tabs');
    }
}
