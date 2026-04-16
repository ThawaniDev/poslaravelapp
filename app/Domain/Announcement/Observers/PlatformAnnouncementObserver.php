<?php

namespace App\Domain\Announcement\Observers;

use App\Domain\Announcement\Jobs\DispatchAnnouncementJob;
use App\Domain\Announcement\Models\PlatformAnnouncement;
use Illuminate\Support\Facades\Log;

class PlatformAnnouncementObserver
{
    public function created(PlatformAnnouncement $announcement): void
    {
        if (! $announcement->send_push && ! $announcement->send_email) {
            return;
        }

        // Only dispatch if the announcement is currently active (not future-scheduled)
        $now = now();
        $startsInFuture = $announcement->display_start_at && $announcement->display_start_at->isFuture();

        if ($startsInFuture) {
            // Schedule delivery for when it becomes active
            $delay = $announcement->display_start_at->diffInSeconds($now);
            DispatchAnnouncementJob::dispatch($announcement->id)->delay($delay);
            Log::info('PlatformAnnouncementObserver: Scheduled dispatch', [
                'id' => $announcement->id,
                'delay_seconds' => $delay,
            ]);
            return;
        }

        // Dispatch immediately
        DispatchAnnouncementJob::dispatch($announcement->id);
    }
}
