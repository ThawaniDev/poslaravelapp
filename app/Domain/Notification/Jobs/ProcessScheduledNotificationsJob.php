<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\NotificationSchedule;
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
        NotificationCustom::create([
            'user_id' => $userId,
            'store_id' => $schedule->store_id,
            'category' => $schedule->category ?? 'system',
            'title' => $schedule->title ?? 'Scheduled Notification',
            'message' => $schedule->message ?? '',
            'priority' => $schedule->priority ?? 'normal',
            'channel' => $schedule->channel ?? 'in_app',
            'is_read' => false,
            'created_at' => Carbon::now(),
        ]);
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
