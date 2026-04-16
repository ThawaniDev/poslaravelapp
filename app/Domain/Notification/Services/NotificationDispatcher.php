<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\FcmToken;
use Illuminate\Support\Facades\Log;

/**
 * High-level notification dispatcher.
 *
 * Call this service from observers, listeners, or controllers to trigger
 * notifications. It creates an in-app notification AND sends an FCM push.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly FcmService $fcm,
        private readonly NotificationTemplateService $templates,
    ) {}

    // ─── Dispatch to User ────────────────────────────────

    /**
     * Send a notification to a single user by event key.
     *
     * Creates an in-app notification and sends a push notification.
     */
    public function toUser(
        string $userId,
        string $eventKey,
        array $variables = [],
        ?string $category = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        string $locale = 'en',
        string $priority = 'normal',
    ): void {
        $rendered = $this->templates->render($eventKey, NotificationChannel::Push, $variables, $locale);

        if (! $rendered) {
            // No template — use event catalog defaults
            $events = NotificationTemplateService::allEvents();
            $meta = $events[$eventKey] ?? null;
            $rendered = [
                'title' => $meta['description'] ?? $eventKey,
                'body' => collect($variables)->map(fn ($v, $k) => "{$k}: {$v}")->join(', '),
            ];
        }

        $title = $rendered['title'];
        $body = $rendered['body'];
        $resolvedCategory = $category ?? explode('.', $eventKey)[0] ?? 'system';

        // 1. Create in-app notification
        $this->createInAppNotification(
            userId: $userId,
            category: $resolvedCategory,
            title: $title,
            body: $body,
            eventKey: $eventKey,
            referenceId: $referenceId,
            referenceType: $referenceType,
            priority: $priority,
            data: $variables,
        );

        // 2. Send FCM push
        $this->fcm->sendToUser($userId, $title, $body, [
            'event_key' => $eventKey,
            'category' => $resolvedCategory,
            'reference_id' => $referenceId ?? '',
            'reference_type' => $referenceType ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);
    }

    // ─── Dispatch to Store ───────────────────────────────

    /**
     * Send a notification to all users in a store.
     */
    public function toStore(
        string $storeId,
        string $eventKey,
        array $variables = [],
        ?string $category = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        string $locale = 'en',
        string $priority = 'normal',
    ): void {
        $userIds = \App\Domain\Auth\Models\User::where('store_id', $storeId)
            ->pluck('id')
            ->toArray();

        foreach ($userIds as $userId) {
            $this->toUser(
                userId: $userId,
                eventKey: $eventKey,
                variables: $variables,
                category: $category,
                referenceId: $referenceId,
                referenceType: $referenceType,
                locale: $locale,
                priority: $priority,
            );
        }
    }

    // ─── Dispatch to Store Owner ─────────────────────────

    /**
     * Send a notification to the store owner only.
     */
    public function toStoreOwner(
        string $storeId,
        string $eventKey,
        array $variables = [],
        ?string $category = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        string $locale = 'en',
        string $priority = 'normal',
    ): void {
        $owner = \App\Domain\Auth\Models\User::where('store_id', $storeId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'store_owner'))
            ->first();

        if (! $owner) {
            // Fallback: send to first user of the store
            $owner = \App\Domain\Auth\Models\User::where('store_id', $storeId)->first();
        }

        if ($owner) {
            $this->toUser(
                userId: $owner->id,
                eventKey: $eventKey,
                variables: $variables,
                category: $category,
                referenceId: $referenceId,
                referenceType: $referenceType,
                locale: $locale,
                priority: $priority,
            );
        }
    }

    // ─── Quick Notification (no template) ────────────────

    /**
     * Send a quick notification with explicit title/body (no template lookup).
     */
    public function quickSend(
        string $userId,
        string $title,
        string $body,
        string $category = 'system',
        ?string $referenceId = null,
        ?string $referenceType = null,
        string $priority = 'normal',
    ): void {
        $this->createInAppNotification(
            userId: $userId,
            category: $category,
            title: $title,
            body: $body,
            eventKey: 'quick_send',
            referenceId: $referenceId,
            referenceType: $referenceType,
            priority: $priority,
        );

        $this->fcm->sendToUser($userId, $title, $body, [
            'category' => $category,
            'reference_id' => $referenceId ?? '',
            'reference_type' => $referenceType ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);
    }

    // ─── Create In-App Notification ──────────────────────

    private function createInAppNotification(
        string $userId,
        string $category,
        string $title,
        string $body,
        string $eventKey,
        ?string $referenceId,
        ?string $referenceType,
        string $priority = 'normal',
        array $data = [],
    ): NotificationCustom {
        return NotificationCustom::create([
            'user_id' => $userId,
            'category' => $category,
            'title' => $title,
            'message' => $body,
            'priority' => $priority,
            'metadata' => array_filter([
                'event_key' => $eventKey,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'variables' => $data,
            ]),
        ]);
    }
}
