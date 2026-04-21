<?php

namespace App\Domain\AppUpdateManagement\Controllers\Api;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderAppReleaseController extends BaseApiController
{
    /**
     * GET /api/v2/app-releases
     *
     * Recent app releases visible to the provider, optionally filtered
     * by ?platform=ios|android|web|windows|macos and ?channel=stable|beta|dev.
     * Returns the latest 20 active releases.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AppRelease::query()
            ->where('is_active', true)
            ->orderByDesc('released_at')
            ->orderByDesc('created_at');

        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }

        $releases = $query->limit(20)->get();

        return $this->success([
            'releases' => $releases->map(fn (AppRelease $r) => $this->transform($r))->all(),
            'total' => $releases->count(),
        ]);
    }

    /**
     * GET /api/v2/app-releases/latest?platform=ios&channel=stable
     *
     * Returns the latest active release for a given platform & channel,
     * or null when no release exists.
     */
    public function latest(Request $request): JsonResponse
    {
        $platform = $request->query('platform', 'ios');
        $channel = $request->query('channel', 'stable');

        $release = AppRelease::query()
            ->where('is_active', true)
            ->where('platform', $platform)
            ->where('channel', $channel)
            ->orderByDesc('released_at')
            ->orderByDesc('created_at')
            ->first();

        return $this->success([
            'release' => $release ? $this->transform($release) : null,
        ]);
    }

    private function transform(AppRelease $r): array
    {
        return [
            'id' => $r->id,
            'version_number' => $r->version_number,
            'build_number' => $r->build_number,
            'platform' => $r->platform?->value,
            'channel' => $r->channel?->value,
            'download_url' => $r->download_url,
            'store_url' => $r->store_url,
            'release_notes' => $r->release_notes,
            'release_notes_ar' => $r->release_notes_ar,
            'is_force_update' => (bool) $r->is_force_update,
            'min_supported_version' => $r->min_supported_version,
            'rollout_percentage' => (int) $r->rollout_percentage,
            'submission_status' => $r->submission_status?->value,
            'released_at' => $r->released_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
