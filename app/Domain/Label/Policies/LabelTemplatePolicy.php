<?php

namespace App\Domain\Label\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Label\Models\LabelTemplate;
use Illuminate\Foundation\Auth\User as Authenticatable;

class LabelTemplatePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('label_view');
    }

    public function view(Authenticatable $user, LabelTemplate $template): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $template->organization_id
            && $user->hasPermissionTo('label_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('label_create');
    }

    public function update(Authenticatable $user, LabelTemplate $template): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $template->organization_id
            && $user->hasPermissionTo('label_update');
    }

    public function delete(Authenticatable $user, LabelTemplate $template): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->organization_id === $template->organization_id
            && $user->hasPermissionTo('label_delete');
    }

    public function print(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('label_print');
    }
}
