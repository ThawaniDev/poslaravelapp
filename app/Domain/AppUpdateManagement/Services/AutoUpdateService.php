<?php

namespace App\Domain\AppUpdateManagement\Services;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;

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
}
