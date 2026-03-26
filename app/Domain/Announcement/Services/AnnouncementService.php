<?php

namespace App\Domain\Announcement\Services;

use App\Domain\Announcement\Models\PlatformAnnouncement;
use App\Domain\Announcement\Models\PlatformAnnouncementDismissal;
use App\Domain\Core\Models\Store;
use Illuminate\Support\Collection;

class AnnouncementService
{
    /**
     * Get active announcements for a given store, excluding dismissed ones.
     */
    public function getActiveForStore(string $storeId): Collection
    {
        $now = now();

        $dismissedIds = PlatformAnnouncementDismissal::where('store_id', $storeId)
            ->pluck('announcement_id');

        return PlatformAnnouncement::query()
            ->where(fn ($q) => $q->whereNull('display_start_at')->orWhere('display_start_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('display_end_at')->orWhere('display_end_at', '>=', $now))
            ->whereNotIn('id', $dismissedIds)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Dismiss an announcement for a store.
     */
    public function dismiss(string $announcementId, string $storeId): PlatformAnnouncementDismissal
    {
        return PlatformAnnouncementDismissal::firstOrCreate(
            [
                'announcement_id' => $announcementId,
                'store_id' => $storeId,
            ],
            [
                'dismissed_at' => now(),
            ]
        );
    }

    /**
     * Get stores targeted by an announcement's target_filter.
     */
    public function getTargetedStores(PlatformAnnouncement $announcement): Collection
    {
        $filter = $announcement->target_filter ?? ['scope' => 'all'];
        $query = Store::where('is_active', true);

        if (($filter['scope'] ?? 'all') !== 'all') {
            if (!empty($filter['plan_ids'])) {
                $query->whereHas('organization.subscription', fn ($q) =>
                    $q->whereIn('subscription_plan_id', $filter['plan_ids'])
                );
            }
            if (!empty($filter['region'])) {
                $query->where('city', $filter['region']);
            }
            if (!empty($filter['store_ids'])) {
                $query->whereIn('id', $filter['store_ids']);
            }
        }

        return $query->get();
    }
}
