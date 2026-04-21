<?php

namespace App\Domain\Notification\Observers;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\Core\Models\Store;
use App\Domain\Notification\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fires system.update_available to every active store when a new active
 * AppRelease is created (or an existing release is flipped to active).
 */
class AppReleaseNotificationObserver
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function created(AppRelease $release): void
    {
        if (! $release->is_active) {
            return;
        }

        $this->broadcast($release);
    }

    public function updated(AppRelease $release): void
    {
        if (! $release->wasChanged('is_active')) {
            return;
        }

        if (! $release->is_active) {
            return;
        }

        $this->broadcast($release);
    }

    private function broadcast(AppRelease $release): void
    {
        try {
            $variables = [
                'version' => $release->version_number ?? '',
                'release_notes_summary' => Str::limit(
                    (string) ($release->release_notes ?? ''),
                    200,
                ),
            ];

            $storeIds = Store::where('is_active', true)->pluck('id');
            foreach ($storeIds as $storeId) {
                $this->dispatcher->toStoreOwner(
                    storeId: $storeId,
                    eventKey: 'system.update_available',
                    variables: $variables,
                    category: 'system',
                    referenceId: $release->id,
                    referenceType: 'app_release',
                );
            }
        } catch (\Throwable $e) {
            Log::error('AppReleaseNotificationObserver::broadcast failed', ['error' => $e->getMessage()]);
        }
    }
}
