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
     * List notifications for the authenticated user.
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
     * Create a notification for the authenticated user.
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
     * GET /api/v2/notifications/unread-count
     * Get the number of unread notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->unreadCount($request->user()->id);

        return $this->success(['unread_count' => $count]);
    }

    /**
     * PUT /api/v2/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $marked = $this->notificationService->markAsRead($id, $request->user()->id);

        if (!$marked) {
            return $this->notFound('Notification not found');
        }

        return $this->success(null, 'Notification marked as read');
    }

    /**
     * PUT /api/v2/notifications/read-all
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user()->id);

        return $this->success(
            ['marked_count' => $count],
            'All notifications marked as read',
        );
    }

    /**
     * DELETE /api/v2/notifications/{id}
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->notificationService->delete($id, $request->user()->id);

        if (!$deleted) {
            return $this->notFound('Notification not found');
        }

        return $this->success(null, 'Notification deleted');
    }

    // ─── Preferences ─────────────────────────────────────

    /**
     * GET /api/v2/notifications/preferences
     * Get notification preferences for the authenticated user.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $prefs = $this->notificationService->getPreferences($request->user()->id);

        return $this->success($prefs);
    }

    /**
     * PUT /api/v2/notifications/preferences
     * Update notification preferences.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $prefs = $this->notificationService->updatePreferences(
            $request->user()->id,
            $request->validated(),
        );

        return $this->success($prefs, 'Preferences updated');
    }

    // ─── FCM Tokens ──────────────────────────────────────

    /**
     * POST /api/v2/notifications/fcm-tokens
     * Register an FCM push notification token.
     */
    public function registerFcmToken(RegisterFcmTokenRequest $request): JsonResponse
    {
        $token = $this->notificationService->registerFcmToken(
            $request->user()->id,
            $request->validated()['token'],
            $request->validated()['device_type'],
        );

        return $this->created(['id' => $token->id, 'token' => $token->token, 'device_type' => $token->device_type], 'FCM token registered');
    }

    /**
     * DELETE /api/v2/notifications/fcm-tokens
     * Remove an FCM token.
     */
    public function removeFcmToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $removed = $this->notificationService->removeFcmToken(
            $request->user()->id,
            $request->input('token'),
        );

        if (!$removed) {
            return $this->notFound('FCM token not found');
        }

        return $this->success(null, 'FCM token removed');
    }
}
