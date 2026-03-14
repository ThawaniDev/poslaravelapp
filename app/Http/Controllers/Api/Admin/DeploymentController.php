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
        if ($request->has('is_mandatory')) {
            $query->where('is_mandatory', filter_var($request->is_mandatory, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('version', 'like', "%{$s}%")
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
            'version'            => 'required|string|max:20',
            'build_number'       => 'nullable|string|max:20',
            'release_notes'      => 'nullable|string',
            'release_notes_ar'   => 'nullable|string',
            'is_mandatory'       => 'nullable|boolean',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'min_os_version'     => 'nullable|string|max:20',
            'download_url'       => 'nullable|string|max:500',
        ]);

        $release = AppRelease::forceCreate([
            'id'                 => Str::uuid()->toString(),
            'platform'           => $request->platform,
            'version'            => $request->version,
            'build_number'       => $request->build_number,
            'release_notes'      => $request->release_notes,
            'release_notes_ar'   => $request->release_notes_ar,
            'is_mandatory'       => $request->boolean('is_mandatory', false),
            'is_active'          => false,
            'rollout_percentage' => $request->integer('rollout_percentage', 100),
            'min_os_version'     => $request->min_os_version,
            'download_url'       => $request->download_url,
            'released_by'        => $request->user('admin-api')?->id,
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
            'version'            => 'sometimes|string|max:20',
            'build_number'       => 'nullable|string|max:20',
            'release_notes'      => 'nullable|string',
            'release_notes_ar'   => 'nullable|string',
            'is_mandatory'       => 'nullable|boolean',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'min_os_version'     => 'nullable|string|max:20',
            'download_url'       => 'nullable|string|max:500',
        ]);

        $release->forceFill($request->only([
            'version', 'build_number', 'release_notes', 'release_notes_ar',
            'is_mandatory', 'rollout_percentage', 'min_os_version', 'download_url',
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
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $query = AppUpdateStat::where('app_release_id', $releaseId)
            ->orderByDesc('date');

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $stats = $query->paginate($request->integer('per_page', 15));

        return $this->success($stats, 'Stats retrieved');
    }

    public function recordStat(Request $request, string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $request->validate([
            'date'             => 'required|date',
            'total_installs'   => 'nullable|integer|min:0',
            'total_updates'    => 'nullable|integer|min:0',
            'total_rollbacks'  => 'nullable|integer|min:0',
        ]);

        $stat = AppUpdateStat::forceCreate([
            'id'               => Str::uuid()->toString(),
            'app_release_id'   => $releaseId,
            'date'             => $request->date,
            'total_installs'   => $request->integer('total_installs', 0),
            'total_updates'    => $request->integer('total_updates', 0),
            'total_rollbacks'  => $request->integer('total_rollbacks', 0),
        ]);

        return $this->created($stat, 'Stat recorded');
    }

    public function releaseSummary(string $releaseId): JsonResponse
    {
        $release = AppRelease::find($releaseId);
        if (!$release) {
            return $this->notFound('Release not found');
        }

        $stats = AppUpdateStat::where('app_release_id', $releaseId);

        $summary = [
            'release'          => $release,
            'total_installs'   => (int) $stats->sum('total_installs'),
            'total_updates'    => (int) $stats->sum('total_updates'),
            'total_rollbacks'  => (int) $stats->sum('total_rollbacks'),
            'days_tracked'     => $stats->count(),
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
                    'version'            => $activeRelease->version,
                    'rollout_percentage' => $activeRelease->rollout_percentage,
                    'released_at'        => $activeRelease->released_at,
                ] : null,
            ];
        }

        return $this->success($overview, 'Platform overview retrieved');
    }
}
