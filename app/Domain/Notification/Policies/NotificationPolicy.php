<?php

namespace App\Domain\Notification\Policies;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Notification\Models\Notification;
use Illuminate\Foundation\Auth\User as Authenticatable;

class NotificationPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('notification_view');
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return $user->hasPermissionTo('notification_create');
    }

    public function managePreferences(Authenticatable $user): bool
    {
        // All authenticated users can manage their own notification preferences
        return true;
    }

    public function manageFcmTokens(Authenticatable $user): bool
    {
        // All authenticated users can manage their own FCM tokens
        return true;
    }
}
