<?php

namespace App\Domain\ThawaniIntegration\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ThawaniStoreConfigPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('thawani_view');
    }

    public function view(Authenticatable $user, ThawaniStoreConfig $config): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->store_id === $config->store_id
            && $user->hasPermissionTo('thawani_view');
    }

    public function manage(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('thawani_manage');
    }
}
