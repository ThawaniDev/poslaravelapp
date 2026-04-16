<?php

namespace App\Domain\Announcement\Controllers\Api;

use App\Domain\Announcement\Models\PlatformAnnouncement;
use App\Domain\Announcement\Services\AnnouncementService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProviderAnnouncementController extends BaseApiController
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {}

    /**
     * GET /api/v2/announcements
     * Get active announcements for the authenticated store.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->store_id ?? $request->query('store_id');

        if (!$storeId) {
            return $this->error('Store ID is required', 400);
        }

        $announcements = $this->announcementService->getActiveForStore($storeId);

        return $this->success([
            'announcements' => $announcements->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'title' => $a->title,
                'title_ar' => $a->title_ar,
                'body' => $a->body,
                'body_ar' => $a->body_ar,
                'is_banner' => $a->is_banner,
                'display_start_at' => $a->display_start_at?->toIso8601String(),
                'display_end_at' => $a->display_end_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
            'total' => $announcements->count(),
        ]);
    }

    /**
     * POST /api/v2/announcements/{id}/dismiss
     * Dismiss an announcement for the authenticated store.
     */
    public function dismiss(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $storeId = $user->store_id ?? $request->input('store_id');

        if (!$storeId) {
            return $this->error('Store ID is required', 400);
        }

        if (!Str::isUuid($id) || !PlatformAnnouncement::find($id)) {
            return $this->notFound('Announcement not found');
        }

        $this->announcementService->dismiss($id, $storeId);

        return $this->success(null, 'Announcement dismissed');
    }
}
