<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\NotificationSchedule;
use App\Domain\Notification\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $dueSchedules = NotificationSchedule::where('is_active', true)
            ->where('next_run_at', '<=', Carbon::now())
            ->limit(100)
            ->get();

        if ($dueSchedules->isEmpty()) {
            return;
        }

        Log::info('ProcessScheduledNotifications: processing ' . $dueSchedules->count() . ' due schedules');

        foreach ($dueSchedules as $schedule) {
            try {
                $this->processSchedule($schedule);
            } catch (\Throwable $e) {
                Log::error('ProcessScheduledNotifications: failed to process schedule', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processSchedule(NotificationSchedule $schedule): void
    {
        // If there's a specific recipient, send to them; otherwise send a store-wide notification
        if ($schedule->recipient_user_id) {
            $this->createNotification($schedule, $schedule->recipient_user_id);
        } elseif ($schedule->store_id) {
            // Get all users for this store
            $userIds = \DB::table('store_staff')
                ->where('store_id', $schedule->store_id)
                ->where('is_active', true)
                ->pluck('user_id')
                ->toArray();

            foreach ($userIds as $userId) {
                $this->createNotification($schedule, $userId);
            }
        }

        $schedule->last_sent_at = Carbon::now();

        if ($schedule->schedule_type === 'recurring' && $schedule->cron_expression) {
            $schedule->next_run_at = $this->calculateNextRun($schedule->cron_expression, $schedule->timezone ?? 'Asia/Riyadh');
        } else {
            // One-off schedule — deactivate
            $schedule->is_active = false;
        }

        $schedule->save();
    }

    private function createNotification(NotificationSchedule $schedule, string $userId): void
    {
        $title = $schedule->title ?? 'Scheduled Notification';
        $message = $schedule->message ?? '';
        $category = $schedule->category ?? 'system';

        NotificationCustom::create([
            'user_id' => $userId,
            'store_id' => $schedule->store_id,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'priority' => $schedule->priority ?? 'normal',
            'channel' => $schedule->channel ?? 'in_app',
            'is_read' => false,
            'created_at' => Carbon::now(),
        ]);

        // Also send FCM push notification
        try {
            $fcm = app(FcmService::class);
            $fcm->sendToUser($userId, $title, $message, [
                'category' => $category,
                'schedule_id' => $schedule->id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessScheduledNotifications: FCM push failed', [
                'user_id' => $userId,
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function calculateNextRun(string $cronExpression, string $timezone): Carbon
    {
        try {
            $cron = new \Cron\CronExpression($cronExpression);
            return Carbon::instance($cron->getNextRunDate('now', 0, false, $timezone));
        } catch (\Throwable) {
            // Fall back to 24h from now if cron expression is invalid
            return Carbon::now()->addDay();
        }
    }
}
