<?php

namespace App\Domain\IndustryFlorist\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\IndustryFlorist\Models\FlowerArrangement;
use Illuminate\Contracts\Auth\Authenticatable;

class IndustryFloristPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('florist.view');
    }

    public function view(Authenticatable $user, FlowerArrangement $arrangement): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        if (! $user->hasPermissionTo('florist.view')) {
            return false;
        }

        return $user->store_id === $arrangement->store_id;
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('florist.manage');
    }

    public function update(Authenticatable $user, FlowerArrangement $arrangement): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        if (! $user->hasPermissionTo('florist.manage')) {
            return false;
        }

        return $user->store_id === $arrangement->store_id;
    }

    public function delete(Authenticatable $user, FlowerArrangement $arrangement): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        if (! $user->hasPermissionTo('florist.manage')) {
            return false;
        }

        return $user->store_id === $arrangement->store_id;
    }
}
