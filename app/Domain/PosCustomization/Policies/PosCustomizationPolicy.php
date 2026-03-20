<?php

namespace App\Domain\PosCustomization\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PosCustomizationPolicy
{
    public function view(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('customization_view');
    }

    public function update(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('customization_update');
    }
}
