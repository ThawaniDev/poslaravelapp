<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

/**
 * Fires staff.unauthorized_access when an authentication attempt fails.
 *
 * Without an explicit store context, this falls back to using the email
 * to look up the user's store. If we cannot resolve a store, the event
 * is silently skipped.
 */
class FireUnauthorizedAccessNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(Failed $event): void
    {
        try {
            $email = $event->credentials['email'] ?? null;
            if (! $email) {
                return;
            }

            $user = \App\Domain\Auth\Models\User::where('email', $email)->first();
            if (! $user || ! $user->store_id) {
                return;
            }

            $this->dispatcher->toStoreOwner(
                storeId: $user->store_id,
                eventKey: 'staff.unauthorized_access',
                variables: [
                    'user_name' => $user->name ?? $email,
                    'attempted_action' => 'login',
                    'store_name' => $user->store?->name ?? '',
                ],
                category: 'staff',
                referenceId: (string) $user->id,
                referenceType: 'user',
                priority: 'high',
            );
        } catch (\Throwable $e) {
            Log::error('FireUnauthorizedAccessNotification failed', ['error' => $e->getMessage()]);
        }
    }
}
