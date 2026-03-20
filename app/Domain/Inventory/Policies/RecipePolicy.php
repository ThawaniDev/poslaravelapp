<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Inventory\Models\Recipe;
use Illuminate\Foundation\Auth\User as Authenticatable;

class RecipePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('recipe_view');
    }

    public function view(Authenticatable $user, Recipe $recipe): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $recipe->organization_id
            && $user->hasPermissionTo('recipe_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('recipe_create');
    }

    public function update(Authenticatable $user, Recipe $recipe): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $recipe->organization_id
            && $user->hasPermissionTo('recipe_update');
    }

    public function delete(Authenticatable $user, Recipe $recipe): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $recipe->organization_id
            && $user->hasPermissionTo('recipe_delete');
    }
}
