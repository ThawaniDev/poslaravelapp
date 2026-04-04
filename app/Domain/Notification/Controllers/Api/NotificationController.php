<?php

namespace App\Domain\Notification\Controllers\Api;

use App\Domain\Notification\Requests\CreateNotificationRequest;
use App\Domain\Notification\Requests\ListNotificationRequest;
use App\Domain\Notification\Requests\RegisterFcmTokenRequest;
use App\Domain\Notification\Requests\UpdatePreferencesRequest;
use App\Domain\Notification\Resources\NotificationResource;
use App\Domain\Notification\Services\NotificationService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    // ─── Notifications ───────────────────────────────────

    /**
     * GET /api/v2/notifications
     */
    public function index(ListNotificationRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->list(
            $request->user()->id,
            $request->validated(),
        );

        return $this->success($notifications);
    }

    /**
     * POST /api/v2/notifications
     */
    public function store(CreateNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->create(
            $request->user()->id,
            $request->user()->store_id,
            $request->validated(),
        );

        return $this->created(
            new NotificationResource($notification),
            'Notification created',
        );
    }

    /**
     * POST /api/v2/notifications/batch
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'string|uuid',
            'category' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'channel' => 'nullable|string',
        ]);

        $batch = $this->notificationService->createBatch(
            $request->input('user_ids'),
            $request->user()->store_id,
            $request->only(['category', 'title', 'message', 'priority', 'channel', 'action_url', 'metadata']),
        );

        return $this->created($batch, __('notifications.batch_created'));
    }

    /**
     * DELETE /api/v2/notifications/bulk
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string|uuid',
        ]);

        $count = $this->notificationService->bulkDelete(
            $request->input('ids'),
            $request->user()->id,
        );

        return $this->success(['deleted_count' => $count], __('notifications.bulk_deleted'));
    }

    /**
     * GET /api/v2/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->unreadCount($request->user()->id);

        return $this->success(['unread_count' => $count]);
    }

    /**
     * GET /api/v2/notifications/unread-count-by-category
     */
    public function unreadCountByCategory(Request $request): JsonResponse
    {
        $counts = $this->notificationService->unreadCountByCategory($request->user()->id);

        return $this->success($counts);
    }

    /**
     * GET /api/v2/notifications/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->notificationService->userStats($request->user()->id);

        return $this->success($stats);
    }

    /**
     * PUT /api/v2/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $readVia = $request->input('read_via', 'click');
        $marked = $this->notificationService->markAsRead($id, $request->user()->id, $readVia);

        if (!$marked) {
            return $this->notFound(__('notifications.not_found'));
        }

        return $this->success(null, __('notifications.marked_read'));
    }

    /**
     * PUT /api/v2/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user()->id);

        return $this->success(
            ['marked_count' => $count],
            __('notifications.all_marked_read'),
        );
    }

    /**
     * DELETE /api/v2/notifications/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->notificationService->delete($id, $request->user()->id);

        if (!$deleted) {
            return $this->notFound(__('notifications.not_found'));
        }

        return $this->success(null, __('notifications.deleted'));
    }

    // ─── Delivery Logs ───────────────────────────────────

    /**
     * GET /api/v2/notifications/delivery-logs
     */
    public function deliveryLogs(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        if (!$storeId) {
            return $this->error(__('notifications.store_required'), 422);
        }

        $logs = $this->notificationService->listDeliveryLogs(
            $storeId,
            $request->only(['channel', 'status', 'provider', 'per_page']),
        );

        return $this->success($logs, __('notifications.delivery_logs_fetched'));
    }

    /**
     * GET /api/v2/notifications/delivery-stats
     */
    public function deliveryStats(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        if (!$storeId) {
            return $this->error(__('notifications.store_required'), 422);
        }

        $days = (int) $request->query('days', 7);
        $stats = $this->notificationService->deliveryStats($storeId, $days);

        return $this->success($stats);
    }

    // ─── Preferences ─────────────────────────────────────

    /**
     * GET /api/v2/notifications/preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $prefs = $this->notificationService->getPreferences($request->user()->id);

        return $this->success($prefs);
    }

    /**
     * PUT /api/v2/notifications/preferences
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $prefs = $this->notificationService->updatePreferences(
            $request->user()->id,
            $request->validated(),
        );

        return $this->success($prefs, __('notifications.preferences_updated'));
    }

    // ─── Sound Configuration ─────────────────────────────

    /**
     * GET /api/v2/notifications/sound-configs
     */
    public function getSoundConfigs(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        if (!$storeId) {
            return $this->error(__('notifications.store_required'), 422);
        }

        $configs = $this->notificationService->getSoundConfigs($storeId);

        return $this->success($configs);
    }

    /**
     * PUT /api/v2/notifications/sound-configs/{eventKey}
     */
    public function updateSoundConfig(Request $request, string $eventKey): JsonResponse
    {
        $request->validate([
            'is_enabled' => 'required|boolean',
            'sound_file' => 'nullable|string|max:255',
            'volume' => 'nullable|integer|min:0|max:100',
            'repeat_count' => 'nullable|integer|min:1|max:10',
            'repeat_interval_seconds' => 'nullable|integer|min:1|max:60',
        ]);

        $storeId = $request->user()->store_id;
        if (!$storeId) {
            return $this->error(__('notifications.store_required'), 422);
        }

        $config = $this->notificationService->updateSoundConfig(
            $storeId,
            $eventKey,
            $request->only(['is_enabled', 'sound_file', 'volume', 'repeat_count', 'repeat_interval_seconds']),
        );

        return $this->success($config, __('notifications.sound_config_updated'));
    }

    // ─── Schedules ───────────────────────────────────────

    /**
     * GET /api/v2/notifications/schedules
     */
    public function listSchedules(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        if (!$storeId) {
            return $this->error(__('notifications.store_required'), 422);
        }

        $schedules = $this->notificationService->listSchedules($storeId);

        return $this->success($schedules);
    }

    /**
     * POST /api/v2/notifications/schedules
     */
    public function createSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'event_key' => 'required|string',
            'channel' => 'required|string',
            'recipient_user_id' => 'nullable|string|uuid',
            'recipient_group' => 'nullable|string',
            'variables' => 'nullable|array',
            'schedule_type' => 'required|string|in:once,recurring',
            'scheduled_at' => 'required|date|after:now',
            'cron_expression' => 'nullable|string',
            'timezone' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        if (!$storeId) {
            return $this->error(__('notifications.store_required'), 422);
        }

        $schedule = $this->notificationService->createSchedule(
            array_merge($request->validated(), [
                'store_id' => $storeId,
                'created_by' => $request->user()->id,
                'is_active' => true,
            ]),
        );

        return $this->created($schedule, __('notifications.schedule_created'));
    }

    /**
     * PUT /api/v2/notifications/schedules/{id}/cancel
     */
    public function cancelSchedule(string $id): JsonResponse
    {
        $cancelled = $this->notificationService->cancelSchedule($id);

        if (!$cancelled) {
            return $this->notFound(__('notifications.schedule_not_found'));
        }

        return $this->success(null, __('notifications.schedule_cancelled'));
    }

    // ─── FCM Tokens ──────────────────────────────────────

    /**
     * POST /api/v2/notifications/fcm-tokens
     */
    public function registerFcmToken(RegisterFcmTokenRequest $request): JsonResponse
    {
        $token = $this->notificationService->registerFcmToken(
            $request->user()->id,
            $request->validated()['token'],
            $request->validated()['device_type'],
        );

        return $this->created(
            ['id' => $token->id, 'token' => $token->token, 'device_type' => $token->device_type],
            __('notifications.fcm_token_registered'),
        );
    }

    /**
     * DELETE /api/v2/notifications/fcm-tokens
     */
    public function removeFcmToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $removed = $this->notificationService->removeFcmToken(
            $request->user()->id,
            $request->input('token'),
        );

        if (!$removed) {
            return $this->notFound(__('notifications.fcm_token_not_found'));
        }

        return $this->success(null, __('notifications.fcm_token_removed'));
    }
}
