<?php

namespace App\Domain\AppUpdateManagement\Jobs;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckAutoRollback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Threshold: if more than this % of update attempts fail, rollback.
     * Can be overridden via system_settings key `updates.auto_rollback_failure_percent`.
     */
    private const FAILURE_THRESHOLD_PERCENT = 10;

    /**
     * Minimum attempts before evaluating (avoid premature rollback).
     */
    private const MIN_ATTEMPTS = 10;

    /**
     * Only evaluate releases published within the last N days.
     */
    private const EVAL_WINDOW_DAYS = 1;

    public function handle(): void
    {
        $threshold = $this->resolveThreshold();

        $activeReleases = AppRelease::where('is_active', true)
            ->where('released_at', '>=', now()->subDays(self::EVAL_WINDOW_DAYS))
            ->get();

        foreach ($activeReleases as $release) {
            $totalAttempts = AppUpdateStat::where('app_release_id', $release->id)->count();
            $failedAttempts = AppUpdateStat::where('app_release_id', $release->id)
                ->where('status', 'failed')
                ->count();

            if ($totalAttempts < self::MIN_ATTEMPTS) {
                continue;
            }

            $failureRate = ($failedAttempts / $totalAttempts) * 100;

            if ($failureRate >= $threshold) {
                $this->performRollback($release, $failureRate, $totalAttempts, $failedAttempts);
            }
        }
    }

    private function performRollback(
        AppRelease $release,
        float $failureRate,
        int $totalAttempts,
        int $failedAttempts,
    ): void {
        // 1. Deactivate the problematic release
        $release->update(['is_active' => false]);

        $details = [
            'version' => $release->version_number,
            'platform' => $release->platform?->value ?? $release->platform,
            'channel' => $release->channel?->value ?? $release->channel,
            'failure_rate' => round($failureRate, 1),
            'total_attempts' => $totalAttempts,
            'failed_attempts' => $failedAttempts,
        ];

        // 2. Audit log
        AdminActivityLog::create([
            'admin_user_id' => null,
            'action' => 'auto_rollback_release',
            'entity_type' => 'app_release',
            'entity_id' => $release->id,
            'details' => $details,
            'created_at' => now(),
        ]);

        // 3. Create a security alert (visible in the admin security center)
        try {
            SecurityAlert::create([
                'store_id' => null,
                'alert_type' => 'app_crash_loop',
                'severity' => 'critical',
                'title' => "Auto-Rollback: v{$release->version_number} ({$details['platform']}) deactivated",
                'description' => "Failure rate {$details['failure_rate']}% exceeded threshold. "
                    . "{$failedAttempts}/{$totalAttempts} installs failed. "
                    . 'Release has been automatically deactivated.',
                'metadata' => $details,
                'is_resolved' => false,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('CheckAutoRollback: failed to create security alert', ['error' => $e->getMessage()]);
        }

        // 4. Notify all Super Admins via database notification
        try {
            $superAdmins = AdminUser::whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
                ->orWhereHas('permissions', fn ($q) => $q->where('name', 'app_updates.rollback'))
                ->get();

            if ($superAdmins->isNotEmpty()) {
                Notification::send(
                    $superAdmins,
                    new \App\Domain\AppUpdateManagement\Notifications\AutoRollbackNotification($release, $details),
                );
            }
        } catch (\Throwable $e) {
            Log::error('CheckAutoRollback: failed to send admin notifications', ['error' => $e->getMessage()]);
        }

        Log::warning(
            "Auto-rollback: v{$release->version_number} ({$details['platform']}) deactivated",
            $details,
        );
    }

    private function resolveThreshold(): float
    {
        try {
            $setting = \App\Domain\SystemConfig\Models\SystemSetting::where(
                'key', 'updates.auto_rollback_failure_percent'
            )->first();

            if ($setting && is_numeric($setting->value)) {
                return (float) $setting->value;
            }
        } catch (\Throwable) {
            // fallback to constant
        }

        return self::FAILURE_THRESHOLD_PERCENT;
    }
}

