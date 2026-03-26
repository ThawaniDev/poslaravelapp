<?php

namespace App\Domain\AppUpdateManagement\Jobs;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckAutoRollback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Threshold: if more than this % of update attempts fail, rollback.
     */
    private const FAILURE_THRESHOLD_PERCENT = 30;

    /**
     * Minimum attempts before evaluating (avoid premature rollback).
     */
    private const MIN_ATTEMPTS = 10;

    public function handle(): void
    {
        $activeReleases = AppRelease::where('is_active', true)
            ->where('released_at', '>=', now()->subDays(7))
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

            if ($failureRate >= self::FAILURE_THRESHOLD_PERCENT) {
                $release->update(['is_active' => false]);

                AdminActivityLog::create([
                    'admin_user_id' => null,
                    'action' => 'auto_rollback_release',
                    'entity_type' => 'app_release',
                    'entity_id' => $release->id,
                    'details' => [
                        'version' => $release->version_number,
                        'platform' => $release->platform?->value ?? $release->platform,
                        'failure_rate' => round($failureRate, 1),
                        'total_attempts' => $totalAttempts,
                        'failed_attempts' => $failedAttempts,
                    ],
                    'created_at' => now(),
                ]);

                Log::warning("Auto-rollback: Released {$release->version_number} deactivated (failure rate: {$failureRate}%)");
            }
        }
    }
}
