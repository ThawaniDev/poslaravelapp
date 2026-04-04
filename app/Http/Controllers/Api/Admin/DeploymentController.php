<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeploymentController extends BaseApiController
{
    // ──────────────── App Releases ────────────────

    public function listReleases(Request $request): JsonResponse
    {
        $query = AppRelease::query()->orderByDesc('created_at');

        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('is_force_update')) {
            $query->where('is_force_update', filter_var($request->is_force_update, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('version_number', 'like', "%{$s}%")
                  ->orWhere('release_notes', 'like', "%{$s}%")
                  ->orWhere('release_notes_ar', 'like', "%{$s}%");
            });
        }

        $releases = $query->paginate($request->integer('per_page', 15));

        return $this->success($releases, 'Releases retrieved');
    }

    public function createRelease(Request $request): JsonResponse
    {
        $request->validate([
            'platform'           => 'required|string|in:windows,macos,ios,android',
            'version_number'     => 'required|string|max:20',
            'channel'            => 'nullable|string|in:stable,beta,testflight,internal_test',
            'build_number'       => 'nullable|string|max:20',
            'release_notes'      => 'nullable|string',
            'release_notes_ar'   => 'nullable|string',
            'is_force_update'    => 'nullable|boolean',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'min_supported_version' => 'nullable|string|max:20',
            'download_url'       => 'required|string|max:500',
            'store_url'          => 'nullable|string|max:500',
        ]);

        $release = AppRelease::forceCreate([
            'id'                    => Str::uuid()->toString(),
            'platform'              => $request->platform,
            'version_number'        => $request->version_number,
            'channel'               => $request->input('channel', 'stable'),
            'build_number'          => $request->build_number,
            'release_notes'         => $request->release_notes,
            'release_notes_ar'      => $request->release_notes_ar,
            'is_force_update'       => $request->boolean('is_force_update', false),
            'is_active'             => false,
            'rollout_percentage'    => $request->integer('rollout_percentage', 0),
            'min_supported_version' => $request->min_supported_version,
            'download_url'          => $request->download_url,
            'store_url'             => $request->store_url,
        ]);

        return $this->created($release, 'Release created');
    }

    public function showRelease(string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        return $this->success($release, 'Release retrieved');
    }

    public function updateRelease(Request $request, string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $request->validate([
            'version_number'        => 'sometimes|string|max:20',
            'build_number'          => 'nullable|string|max:20',
            'release_notes'         => 'nullable|string',
            'release_notes_ar'      => 'nullable|string',
            'is_force_update'       => 'nullable|boolean',
            'rollout_percentage'    => 'nullable|integer|min:0|max:100',
            'min_supported_version' => 'nullable|string|max:20',
            'download_url'          => 'nullable|string|max:500',
            'store_url'             => 'nullable|string|max:500',
        ]);

        $release->forceFill($request->only([
            'version_number', 'build_number', 'release_notes', 'release_notes_ar',
            'is_force_update', 'rollout_percentage', 'min_supported_version', 'download_url', 'store_url',
        ]))->save();

        return $this->success($release->fresh(), 'Release updated');
    }

    public function activateRelease(string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $release->forceFill(['is_active' => true, 'released_at' => now()])->save();

        return $this->success($release->fresh(), 'Release activated');
    }

    public function deactivateRelease(string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $release->forceFill(['is_active' => false])->save();

        return $this->success($release->fresh(), 'Release deactivated');
    }

    public function updateRollout(Request $request, string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $request->validate([
            'rollout_percentage' => 'required|integer|min:0|max:100',
        ]);

        $release->forceFill(['rollout_percentage' => $request->rollout_percentage])->save();

        return $this->success($release->fresh(), 'Rollout percentage updated');
    }

    public function deleteRelease(string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $release->delete();

        return $this->success(null, 'Release deleted');
    }

    // ──────────────── Update Stats ────────────────

    public function listStats(Request $request, string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) return $this->notFound('Release not found');

        $query = AppUpdateStat::where('app_release_id', $releaseId)
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $stats = $query->paginate($request->integer('per_page', 15));

        return $this->success($stats, 'Stats retrieved');
    }

    public function recordStat(Request $request, string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) return $this->notFound('Release not found');

        $request->validate([
            'store_id'      => 'required|uuid',
            'status'        => 'required|string|in:pending,downloading,installed,failed',
            'error_message' => 'nullable|string',
        ]);

        $stat = AppUpdateStat::forceCreate([
            'id'               => Str::uuid()->toString(),
            'app_release_id'   => $releaseId,
            'store_id'         => $request->store_id,
            'status'           => $request->status,
            'error_message'    => $request->error_message,
        ]);

        return $this->created($stat, 'Stat recorded');
    }

    public function releaseSummary(string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) return $this->notFound('Release not found');

        $stats = AppUpdateStat::where('app_release_id', $releaseId);

        $statusCounts = AppUpdateStat::where('app_release_id', $releaseId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $summary = [
            'release'        => $release,
            'total_stores'   => (int) $stats->count(),
            'installed'      => (int) ($statusCounts['installed'] ?? 0),
            'pending'        => (int) ($statusCounts['pending'] ?? 0),
            'downloading'    => (int) ($statusCounts['downloading'] ?? 0),
            'failed'         => (int) ($statusCounts['failed'] ?? 0),
        ];

        return $this->success($summary, 'Release summary retrieved');
    }

    // ──────────────── Platform Overview ────────────────

    public function platformOverview(): JsonResponse
    {
        $platforms = ['windows', 'macos', 'ios', 'android'];
        $overview = [];

        foreach ($platforms as $platform) {
            $releases = AppRelease::where('platform', $platform);
            $activeRelease = AppRelease::where('platform', $platform)
                ->where('is_active', true)
                ->orderByDesc('released_at')
                ->first();

            $overview[] = [
                'platform'       => $platform,
                'total_releases' => $releases->count(),
                'active_release' => $activeRelease ? [
                    'id'                 => $activeRelease->id,
                    'version_number'     => $activeRelease->version_number,
                    'rollout_percentage' => $activeRelease->rollout_percentage,
                    'released_at'        => $activeRelease->released_at,
                ] : null,
            ];
        }

        return $this->success($overview, 'Platform overview retrieved');
    }
}
