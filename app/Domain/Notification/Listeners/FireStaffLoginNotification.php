<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

/**
 * Fires staff.login when a user authenticates successfully.
 */
class FireStaffLoginNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(Login $event): void
    {
        try {
            $user = $event->user;
            if (! $user || ! method_exists($user, 'getAttribute')) {
                return;
            }

            $storeId = $user->getAttribute('store_id');
            if (! $storeId) {
                return;
            }

            $store = method_exists($user, 'store') ? $user->store : null;
            $deviceName = request()?->userAgent() ?? 'Unknown device';

            $this->dispatcher->toStoreOwner(
                storeId: $storeId,
                eventKey: 'staff.login',
                variables: [
                    'user_name' => $user->getAttribute('name') ?? '—',
                    'device_name' => substr($deviceName, 0, 80),
                    'store_name' => $store?->name ?? '',
                ],
                category: 'staff',
                referenceId: (string) $user->getKey(),
                referenceType: 'user',
            );
        } catch (\Throwable $e) {
            Log::error('FireStaffLoginNotification failed', ['error' => $e->getMessage()]);
        }
    }
}
