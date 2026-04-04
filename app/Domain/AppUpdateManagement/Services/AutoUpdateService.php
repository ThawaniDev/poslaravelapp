<?php

namespace App\Domain\AppUpdateManagement\Services;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use Illuminate\Support\Facades\DB;

class AutoUpdateService
{
    public function checkForUpdate(string $storeId, string $currentVersion, string $platform, string $channel = 'stable'): array
    {
        $release = AppRelease::where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        if (! $release) {
            return [
                'update_available' => false,
                'current_version' => $currentVersion,
            ];
        }

        $isNewer = version_compare($release->version_number, $currentVersion, '>');
        $isForced = $release->is_force_update
            && $release->min_supported_version
            && version_compare($currentVersion, $release->min_supported_version, '<');

        return [
            'update_available' => $isNewer,
            'current_version' => $currentVersion,
            'latest_version' => $release->version_number,
            'is_force_update' => $isForced,
            'min_supported_version' => $release->min_supported_version,
            'download_url' => $release->download_url,
            'store_url' => $release->store_url,
            'release_notes' => $release->release_notes,
            'release_notes_ar' => $release->release_notes_ar,
            'build_number' => $release->build_number,
            'released_at' => $release->released_at?->toIso8601String(),
            'release_id' => $release->id,
        ];
    }

    public function reportStatus(string $storeId, string $releaseId, string $status, ?string $errorMessage = null): array
    {
        $stat = AppUpdateStat::updateOrCreate(
            ['store_id' => $storeId, 'app_release_id' => $releaseId],
            ['status' => $status, 'error_message' => $errorMessage],
        );

        return $stat->toArray();
    }

    public function getChangelog(string $platform, string $channel = 'stable', int $limit = 10): array
    {
        $releases = AppRelease::where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->limit($limit)
            ->get(['id', 'version_number', 'build_number', 'release_notes', 'release_notes_ar', 'is_force_update', 'released_at']);

        return $releases->toArray();
    }

    public function getUpdateHistory(string $storeId): array
    {
        $stats = AppUpdateStat::where('store_id', $storeId)
            ->with('appRelease:id,version_number,platform,released_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return $stats->toArray();
    }

    public function getCurrentVersion(string $storeId, string $platform): array
    {
        $installed = AppUpdateStat::where('store_id', $storeId)
            ->where('status', 'installed')
            ->whereHas('appRelease', function ($q) use ($platform) {
                $q->where('platform', $platform);
            })
            ->with('appRelease:id,version_number,build_number,platform')
            ->orderByDesc('id')
            ->first();

        if (! $installed) {
            return ['version' => null, 'platform' => $platform];
        }

        return [
            'version' => $installed->appRelease->version_number ?? null,
            'build_number' => $installed->appRelease->build_number ?? null,
            'platform' => $platform,
            'status' => $installed->status?->value ?? $installed->status,
        ];
    }

    /**
     * Get the update manifest for a specific version (files, checksums, sizes).
     */
    public function getManifest(string $version, string $platform, string $channel = 'stable'): ?array
    {
        $release = AppRelease::where('version_number', $version)
            ->where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        if (!$release) {
            return null;
        }

        return [
            'version' => $release->version_number,
            'build_number' => $release->build_number,
            'platform' => $release->platform->value ?? $release->platform,
            'channel' => $release->channel->value ?? $release->channel,
            'download_url' => $release->download_url,
            'store_url' => $release->store_url,
            'checksum' => hash('sha256', $release->version_number . $release->build_number),
            'is_force_update' => $release->is_force_update,
            'min_supported_version' => $release->min_supported_version,
            'release_notes' => $release->release_notes,
            'release_notes_ar' => $release->release_notes_ar,
            'released_at' => $release->released_at?->toIso8601String(),
            'rollout_percentage' => $release->rollout_percentage,
        ];
    }

    /**
     * Get download info for a specific version.
     */
    public function getDownloadInfo(string $version, string $platform, string $channel = 'stable'): ?array
    {
        $release = AppRelease::where('version_number', $version)
            ->where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        if (!$release) {
            return null;
        }

        return [
            'version' => $release->version_number,
            'download_url' => $release->download_url,
            'store_url' => $release->store_url,
            'checksum' => hash('sha256', $release->version_number . $release->build_number),
            'build_number' => $release->build_number,
            'platform' => $release->platform->value ?? $release->platform,
        ];
    }

    /**
     * Get rollout status across all stores.
     */
    public function getRolloutStatus(string $platform, string $channel = 'stable'): array
    {
        $latestRelease = AppRelease::where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->first();

        if (!$latestRelease) {
            return [
                'has_active_rollout' => false,
            ];
        }

        $stats = AppUpdateStat::where('app_release_id', $latestRelease->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalStores = array_sum($stats);

        return [
            'has_active_rollout' => true,
            'version' => $latestRelease->version_number,
            'rollout_percentage' => $latestRelease->rollout_percentage,
            'is_force_update' => $latestRelease->is_force_update,
            'released_at' => $latestRelease->released_at?->toIso8601String(),
            'stats' => [
                'total_stores' => $totalStores,
                'installed' => $stats['installed'] ?? 0,
                'downloading' => $stats['downloading'] ?? 0,
                'pending' => $stats['pending'] ?? 0,
                'failed' => $stats['failed'] ?? 0,
            ],
            'adoption_rate' => $totalStores > 0
                ? round(($stats['installed'] ?? 0) / $totalStores * 100, 1)
                : 0,
        ];
    }
}
