<?php

namespace App\Domain\SystemConfig\Jobs;

use App\Domain\SystemConfig\Models\FeatureFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Rebuilds the Redis feature-flag cache.
 *
 * Scheduled: every minute via Console\Kernel.
 * Also dispatched on demand whenever a flag is created, updated, or toggled.
 */
class CacheFeatureFlags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Rebuild every 60 seconds from the scheduler. */
    public const TTL_SECONDS = 60;

    public function handle(): void
    {
        $flags = FeatureFlag::all();

        // Full flag list (all flags, for admin reference)
        Cache::put('config:feature_flags_all', $flags->toArray(), self::TTL_SECONDS * 10);

        // Enabled flags only — this is what ConfigController serves to providers
        $enabled = $flags->where('is_enabled', true)->values();
        Cache::put('config:feature_flags', $enabled, self::TTL_SECONDS * 10);
    }
}
