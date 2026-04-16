<?php

namespace App\Domain\Announcement\Jobs;

use App\Domain\Announcement\Models\PlatformAnnouncement;
use App\Domain\Announcement\Services\AnnouncementService;
use App\Domain\Notification\Mail\AnnouncementMail;
use App\Domain\Notification\Services\EmailService;
use App\Domain\Notification\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $announcementId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(AnnouncementService $announcementService, FcmService $fcm): void
    {
        $announcement = PlatformAnnouncement::find($this->announcementId);

        if (! $announcement) {
            Log::warning('DispatchAnnouncementJob: Announcement not found', ['id' => $this->announcementId]);
            return;
        }

        $stores = $announcementService->getTargetedStores($announcement);

        if ($stores->isEmpty()) {
            Log::info('DispatchAnnouncementJob: No targeted stores', ['id' => $announcement->id]);
            return;
        }

        $title = $announcement->title;
        $body = strip_tags($announcement->body);
        $type = $announcement->type->value;
        $pushCount = 0;
        $emailCount = 0;

        foreach ($stores as $store) {
            // Send FCM push to all store users
            if ($announcement->send_push) {
                try {
                    $result = $fcm->sendToStore($store->id, $title, $body, [
                        'type' => 'platform_announcement',
                        'announcement_id' => $announcement->id,
                        'announcement_type' => $type,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]);
                    $pushCount += $result['success'];
                } catch (\Throwable $e) {
                    Log::warning('DispatchAnnouncementJob: Push failed for store', [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Send email to store owner
            if ($announcement->send_email) {
                try {
                    $owner = \App\Domain\Auth\Models\User::where('store_id', $store->id)
                        ->whereHas('roles', fn ($q) => $q->where('name', 'store_owner'))
                        ->first();

                    $recipient = $owner ?? \App\Domain\Auth\Models\User::where('store_id', $store->id)->first();

                    if ($recipient?->email) {
                        EmailService::queue($recipient->email, new AnnouncementMail(
                            announcementTitle: $title,
                            announcementBody: $announcement->body,
                            type: $type,
                        ));
                        $emailCount++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('DispatchAnnouncementJob: Email failed for store', [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('DispatchAnnouncementJob: Completed', [
            'announcement_id' => $announcement->id,
            'stores' => $stores->count(),
            'push_sent' => $pushCount,
            'email_sent' => $emailCount,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DispatchAnnouncementJob: Failed permanently', [
            'announcement_id' => $this->announcementId,
            'error' => $e->getMessage(),
        ]);
    }
}
