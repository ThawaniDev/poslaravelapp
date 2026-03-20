<?php

namespace App\Domain\DeliveryIntegration\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Foundation\Auth\User as Authenticatable;

class DeliveryPlatformConfigPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('delivery_view');
    }

    public function view(Authenticatable $user, DeliveryPlatformConfig $config): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->store_id === $config->store_id
            && $user->hasPermissionTo('delivery_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('delivery_create');
    }

    public function update(Authenticatable $user, DeliveryPlatformConfig $config): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->store_id === $config->store_id
            && $user->hasPermissionTo('delivery_update');
    }

    public function delete(Authenticatable $user, DeliveryPlatformConfig $config): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->store_id === $config->store_id
            && $user->hasPermissionTo('delivery_delete');
    }
}
