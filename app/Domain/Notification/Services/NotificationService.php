<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\FcmToken;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\UserNotificationPreference;
use Illuminate\Support\Carbon;

class NotificationService
{
    // ─── List ────────────────────────────────────────────

    /**
     * List notifications for a user, newest first.
     * Filterable by category and read status.
     */
    public function list(string $userId, array $filters = []): array
    {
        $query = NotificationCustom::forUser($userId)
            ->orderByDesc('created_at');

        if (!empty($filters['category'])) {
            $query->ofCategory($filters['category']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', (bool) $filters['is_read']);
        }

        $limit = min((int) ($filters['limit'] ?? 50), 200);

        return $query->limit($limit)->get()->toArray();
    }

    // ─── Create ──────────────────────────────────────────

    /**
     * Create a notification for a user.
     */
    public function create(string $userId, ?string $storeId, array $data): NotificationCustom
    {
        return NotificationCustom::create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'category' => $data['category'],
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'is_read' => false,
            'created_at' => Carbon::now(),
        ]);
    }

    // ─── Read / Mark ─────────────────────────────────────

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $notificationId, string $userId): bool
    {
        $updated = NotificationCustom::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => true]);

        return $updated > 0;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(string $userId): int
    {
        return NotificationCustom::forUser($userId)
            ->unread()
            ->update(['is_read' => true]);
    }

    /**
     * Delete a notification.
     */
    public function delete(string $notificationId, string $userId): bool
    {
        $deleted = NotificationCustom::where('id', $notificationId)
            ->where('user_id', $userId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Get unread count for a user.
     */
    public function unreadCount(string $userId): int
    {
        return NotificationCustom::forUser($userId)->unread()->count();
    }

    // ─── Preferences ─────────────────────────────────────

    /**
     * Get notification preferences for a user.
     */
    public function getPreferences(string $userId): array
    {
        $pref = UserNotificationPreference::where('user_id', $userId)->first();

        if (!$pref) {
            return [
                'user_id' => $userId,
                'preferences' => $this->defaultPreferences(),
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
            ];
        }

        return [
            'user_id' => $pref->user_id,
            'preferences' => $pref->preferences_json ?? $this->defaultPreferences(),
            'quiet_hours_start' => $pref->quiet_hours_start,
            'quiet_hours_end' => $pref->quiet_hours_end,
        ];
    }

    /**
     * Update notification preferences for a user.
     */
    public function updatePreferences(string $userId, array $data): array
    {
        $pref = UserNotificationPreference::updateOrCreate(
            ['user_id' => $userId],
            [
                'preferences_json' => $data['preferences'] ?? null,
                'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
                'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
                'updated_at' => Carbon::now(),
            ],
        );

        return [
            'user_id' => $pref->user_id,
            'preferences' => $pref->preferences_json ?? $this->defaultPreferences(),
            'quiet_hours_start' => $pref->quiet_hours_start,
            'quiet_hours_end' => $pref->quiet_hours_end,
        ];
    }

    /**
     * Default preferences when none are configured.
     */
    private function defaultPreferences(): array
    {
        return [
            'order_updates' => ['in_app' => true, 'push' => true],
            'promotions' => ['in_app' => true, 'push' => false],
            'inventory_alerts' => ['in_app' => true, 'push' => true],
            'system_updates' => ['in_app' => true, 'push' => false],
        ];
    }

    // ─── FCM Tokens ──────────────────────────────────────

    /**
     * Register an FCM token for a user.
     * If the token already exists, update its device_type.
     */
    public function registerFcmToken(string $userId, string $token, string $deviceType): FcmToken
    {
        return FcmToken::updateOrCreate(
            ['user_id' => $userId, 'token' => $token],
            ['device_type' => $deviceType],
        );
    }

    /**
     * Remove an FCM token for a user.
     */
    public function removeFcmToken(string $userId, string $token): bool
    {
        $deleted = FcmToken::where('user_id', $userId)
            ->where('token', $token)
            ->delete();

        return $deleted > 0;
    }

    /**
     * List FCM tokens for a user.
     */
    public function listFcmTokens(string $userId): array
    {
        return FcmToken::where('user_id', $userId)->get()->toArray();
    }
}
