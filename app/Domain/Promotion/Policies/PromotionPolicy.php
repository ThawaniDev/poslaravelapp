<?php

namespace App\Domain\Promotion\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Promotion\Models\Promotion;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PromotionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('promotion_view');
    }

    public function view(Authenticatable $user, Promotion $promotion): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $promotion->organization_id
            && $user->hasPermissionTo('promotion_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('promotion_create');
    }

    public function update(Authenticatable $user, Promotion $promotion): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $promotion->organization_id
            && $user->hasPermissionTo('promotion_update');
    }

    public function delete(Authenticatable $user, Promotion $promotion): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $promotion->organization_id
            && $user->hasPermissionTo('promotion_delete');
    }
}
