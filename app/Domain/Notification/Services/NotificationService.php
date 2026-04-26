<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\FcmToken;
use App\Domain\Notification\Models\NotificationBatch;
use App\Domain\Notification\Models\NotificationCustom;
use App\Domain\Notification\Models\NotificationDeliveryLog;
use App\Domain\Notification\Models\NotificationReadReceipt;
use App\Domain\Notification\Models\NotificationSchedule;
use App\Domain\Notification\Models\NotificationSoundConfig;
use App\Domain\Notification\Models\UserNotificationPreference;
use Illuminate\Support\Carbon;

class NotificationService
{
    // ─── List ────────────────────────────────────────────

    /**
     * List notifications for a user, newest first.
     * Filterable by category, read status, priority, and date range.
     */
    public function list(string $userId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = NotificationCustom::forUser($userId)
            ->active()
            ->orderByDesc('created_at');

        if (!empty($filters['category'])) {
            $query->ofCategory($filters['category']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', (bool) $filters['is_read']);
        }

        if (!empty($filters['priority'])) {
            $query->ofPriority($filters['priority']);
        }

        if (!empty($filters['since'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['since']));
        }

        $limit = min((int) ($filters['limit'] ?? 50), 200);

        return $query->limit($limit)->get();
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
            'priority' => $data['priority'] ?? 'normal',
            'expires_at' => $data['expires_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'channel' => $data['channel'] ?? 'in_app',
            'is_read' => false,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Create notifications for multiple users (batch).
     */
    public function createBatch(array $userIds, ?string $storeId, array $data): NotificationBatch
    {
        $batch = NotificationBatch::create([
            'store_id' => $storeId,
            'event_key' => $data['category'] ?? 'custom',
            'channel' => $data['channel'] ?? 'in_app',
            'total_recipients' => count($userIds),
            'status' => 'processing',
            'started_at' => now(),
            'created_at' => now(),
        ]);

        $sent = 0;
        $failed = 0;

        foreach ($userIds as $uid) {
            try {
                $this->create($uid, $storeId, $data);
                $sent++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $batch->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'completed' : ($sent === 0 ? 'failed' : 'partial'),
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    // ─── Read / Mark ─────────────────────────────────────

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $notificationId, string $userId, ?string $readVia = 'click'): bool
    {
        $updated = NotificationCustom::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => true, 'read_at' => now()]);

        if ($updated > 0) {
            NotificationReadReceipt::create([
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'read_at' => now(),
                'read_via' => $readVia ?? 'click',
            ]);
        }

        return $updated > 0;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(string $userId): int
    {
        return NotificationCustom::forUser($userId)
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);
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
     * Bulk delete notifications by IDs.
     */
    public function bulkDelete(array $notificationIds, string $userId): int
    {
        return NotificationCustom::whereIn('id', $notificationIds)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Get unread count for a user.
     */
    public function unreadCount(string $userId): int
    {
        return NotificationCustom::forUser($userId)->unread()->active()->count();
    }

    /**
     * Get unread count by category.
     */
    public function unreadCountByCategory(string $userId): array
    {
        return NotificationCustom::forUser($userId)
            ->unread()
            ->active()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
    }

    // ─── Delivery Logs ───────────────────────────────────

    /**
     * List delivery logs for a store.
     */
    public function listDeliveryLogs(string $storeId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = NotificationDeliveryLog::query()
            ->whereHas('notification', fn ($q) => $q->where('store_id', $storeId));

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 50), 200);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Get delivery statistics for a store.
     */
    public function deliveryStats(string $storeId, int $days = 7): array
    {
        $since = now()->subDays($days);

        $logs = NotificationDeliveryLog::query()
            ->whereHas('notification', fn ($q) => $q->where('store_id', $storeId))
            ->where('created_at', '>=', $since);

        $total = (clone $logs)->count();
        $sent = (clone $logs)->where('status', 'sent')->count();
        $delivered = (clone $logs)->where('status', 'delivered')->count();
        $failed = (clone $logs)->where('status', 'failed')->count();
        $avgLatency = (clone $logs)->whereNotNull('latency_ms')->avg('latency_ms');

        return [
            'period_days' => $days,
            'total' => $total,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
            'avg_latency_ms' => $avgLatency ? (int) $avgLatency : null,
        ];
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
                'per_category_channels' => $this->defaultCategoryChannels(),
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'sound_enabled' => true,
                'email_digest' => 'none',
            ];
        }

        return [
            'user_id' => $pref->user_id,
            'preferences' => $pref->preferences_json ?? $this->defaultPreferences(),
            'per_category_channels' => $pref->per_category_channels ?? $this->defaultCategoryChannels(),
            'quiet_hours_start' => $pref->quiet_hours_start,
            'quiet_hours_end' => $pref->quiet_hours_end,
            'sound_enabled' => $pref->sound_enabled ?? true,
            'email_digest' => $pref->email_digest ?? 'none',
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
                'per_category_channels' => $data['per_category_channels'] ?? null,
                'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
                'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
                'sound_enabled' => $data['sound_enabled'] ?? true,
                'email_digest' => $data['email_digest'] ?? 'none',
                'updated_at' => Carbon::now(),
            ],
        );

        return [
            'user_id' => $pref->user_id,
            'preferences' => $pref->preferences_json ?? $this->defaultPreferences(),
            'per_category_channels' => $pref->per_category_channels ?? $this->defaultCategoryChannels(),
            'quiet_hours_start' => $pref->quiet_hours_start,
            'quiet_hours_end' => $pref->quiet_hours_end,
            'sound_enabled' => $pref->sound_enabled ?? true,
            'email_digest' => $pref->email_digest ?? 'none',
        ];
    }

    /**
     * Default preferences when none are configured.
     */
    private function defaultPreferences(): array
    {
        return [
            'order_updates' => ['in_app' => true, 'push' => true, 'email' => false, 'sound' => true],
            'promotions' => ['in_app' => true, 'push' => false, 'email' => false, 'sound' => false],
            'inventory_alerts' => ['in_app' => true, 'push' => true, 'email' => true, 'sound' => true],
            'system_updates' => ['in_app' => true, 'push' => false, 'email' => false, 'sound' => false],
            'payment_alerts' => ['in_app' => true, 'push' => true, 'email' => true, 'sound' => true],
            'staff_events' => ['in_app' => true, 'push' => true, 'email' => false, 'sound' => false],
        ];
    }

    /**
     * Default per-category channel preferences.
     */
    private function defaultCategoryChannels(): array
    {
        return [
            'order' => ['in_app', 'push', 'sound'],
            'inventory' => ['in_app', 'push'],
            'promotion' => ['in_app'],
            'system' => ['in_app'],
            'payment' => ['in_app', 'push', 'email'],
            'staff' => ['in_app', 'push'],
        ];
    }

    // ─── Sound Configuration ─────────────────────────────

    /**
     * Get sound configurations for a store.
     */
    public function getSoundConfigs(string $storeId): array
    {
        return NotificationSoundConfig::forStore($storeId)
            ->orderBy('event_key')
            ->get()
            ->toArray();
    }

    /**
     * Update or create a sound configuration.
     */
    public function updateSoundConfig(string $storeId, string $eventKey, array $data): NotificationSoundConfig
    {
        return NotificationSoundConfig::updateOrCreate(
            ['store_id' => $storeId, 'event_key' => $eventKey],
            $data,
        );
    }

    // ─── Schedules ───────────────────────────────────────

    /**
     * List notification schedules for a store.
     */
    public function listSchedules(string $storeId): array
    {
        return NotificationSchedule::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Create a scheduled notification.
     */
    public function createSchedule(array $data): NotificationSchedule
    {
        return NotificationSchedule::create(array_merge($data, [
            'next_run_at' => $data['scheduled_at'],
        ]));
    }

    /**
     * Cancel a scheduled notification.
     */
    public function cancelSchedule(string $scheduleId): bool
    {
        return NotificationSchedule::where('id', $scheduleId)
            ->update(['is_active' => false]) > 0;
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

    // ─── Statistics ──────────────────────────────────────

    /**
     * Get notification statistics for a user.
     */
    public function userStats(string $userId): array
    {
        $total = NotificationCustom::forUser($userId)->count();
        $unread = NotificationCustom::forUser($userId)->unread()->count();

        $byCategory = NotificationCustom::forUser($userId)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $byChannel = NotificationCustom::forUser($userId)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        $readCount = $total - $unread;
        $deliveryRate = $total > 0 ? round(($readCount / $total) * 100, 1) : 0;

        return [
            'total'         => $total,
            'unread'        => $unread,
            'read'          => $readCount,
            'delivery_rate' => $deliveryRate,
            'by_category'   => $byCategory,
            'by_channel'    => $byChannel,
        ];
    }
}
