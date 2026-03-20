<?php

namespace App\Domain\IndustryPharmacy\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;

class IndustryPharmacyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pharmacy.view');
    }

    public function manage(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pharmacy.manage');
    }

    public function managePrescriptions(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('pharmacy.prescriptions');
    }
}
