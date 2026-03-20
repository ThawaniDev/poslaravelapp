<?php

namespace App\Domain\StaffManagement\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StaffUserPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('staff_view');
    }

    public function view(Authenticatable $user, StaffUser $staffUser): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('staff_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('staff_create');
    }

    public function update(Authenticatable $user, StaffUser $staffUser): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('staff_update');
    }

    public function delete(Authenticatable $user, StaffUser $staffUser): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('staff_delete');
    }

    public function manageAttendance(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('attendance_manage');
    }

    public function manageShifts(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('shift_manage');
    }

    public function manageRoles(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('role_manage');
    }
}
