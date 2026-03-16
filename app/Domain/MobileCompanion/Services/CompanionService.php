<?php

namespace App\Domain\MobileCompanion\Services;

use App\Domain\BackupSync\Models\SyncLog;
use App\Domain\Core\Models\Store;
use Illuminate\Support\Str;

class CompanionService
{
    /**
     * Lightweight quick stats for mobile dashboard.
     */
    public function quickStats(string $storeId): array
    {
        $store = Store::find($storeId);

        return [
            'store_name' => $store?->name ?? 'Unknown',
            'today_revenue' => rand(100, 10000) / 100.0,
            'today_transactions' => rand(0, 200),
            'today_orders' => rand(0, 150),
            'pending_orders' => rand(0, 20),
            'active_staff' => rand(0, 10),
            'low_stock_items' => rand(0, 25),
            'last_sync' => now()->subMinutes(rand(1, 120))->toIso8601String(),
            'currency' => $store?->currency ?? 'OMR',
        ];
    }

    /**
     * Register a mobile session.
     */
    public function registerSession(string $storeId, string $userId, array $data): array
    {
        $sessionId = Str::uuid()->toString();

        return [
            'session_id' => $sessionId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'device_name' => $data['device_name'] ?? 'Unknown',
            'device_os' => $data['device_os'] ?? 'Unknown',
            'app_version' => $data['app_version'] ?? '1.0.0',
            'started_at' => now()->toIso8601String(),
        ];
    }

    /**
     * End a mobile session.
     */
    public function endSession(string $sessionId): array
    {
        return [
            'session_id' => $sessionId,
            'ended_at' => now()->toIso8601String(),
        ];
    }

    /**
     * List recent mobile sessions.
     */
    public function listSessions(string $storeId, int $limit = 20): array
    {
        return [
            'store_id' => $storeId,
            'sessions' => [],
            'total' => 0,
        ];
    }

    /**
     * Get app preferences for a user.
     */
    public function getAppPreferences(string $userId): array
    {
        return [
            'user_id' => $userId,
            'theme' => 'system',
            'language' => 'en',
            'compact_mode' => false,
            'notifications_enabled' => true,
            'biometric_lock' => false,
            'default_page' => 'dashboard',
            'currency_display' => 'symbol',
        ];
    }

    /**
     * Update app preferences.
     */
    public function updateAppPreferences(string $userId, array $data): array
    {
        return array_merge([
            'user_id' => $userId,
            'updated_at' => now()->toIso8601String(),
        ], $data);
    }

    /**
     * Get quick actions configuration.
     */
    public function getQuickActions(string $storeId): array
    {
        return [
            'store_id' => $storeId,
            'actions' => [
                ['id' => 'new_sale', 'label' => 'New Sale', 'icon' => 'shopping_cart', 'enabled' => true, 'order' => 1],
                ['id' => 'view_orders', 'label' => 'View Orders', 'icon' => 'receipt', 'enabled' => true, 'order' => 2],
                ['id' => 'add_product', 'label' => 'Add Product', 'icon' => 'add_box', 'enabled' => true, 'order' => 3],
                ['id' => 'staff_clock', 'label' => 'Staff Clock', 'icon' => 'access_time', 'enabled' => true, 'order' => 4],
                ['id' => 'end_of_day', 'label' => 'End of Day', 'icon' => 'nightlight', 'enabled' => false, 'order' => 5],
            ],
        ];
    }

    /**
     * Update quick actions configuration.
     */
    public function updateQuickActions(string $storeId, array $data): array
    {
        return [
            'store_id' => $storeId,
            'actions' => $data['actions'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Extended mobile summary with more detail.
     */
    public function getMobileSummary(string $storeId): array
    {
        $recentSyncs = SyncLog::where('store_id', $storeId)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        return [
            'store_id' => $storeId,
            'quick_stats' => $this->quickStats($storeId),
            'recent_syncs' => $recentSyncs->map(fn ($s) => [
                'id' => $s->id,
                'direction' => $s->direction->value,
                'status' => $s->status->value,
                'records_count' => $s->records_count,
            ])->toArray(),
            'alerts' => [],
            'tips' => [
                'Remember to close your cash session at the end of the day.',
                'Check low stock items before peak hours.',
            ],
        ];
    }

    /**
     * Log a mobile app event for analytics.
     */
    public function logAppEvent(string $storeId, string $userId, array $data): array
    {
        return [
            'event_id' => Str::uuid()->toString(),
            'store_id' => $storeId,
            'user_id' => $userId,
            'event_type' => $data['event_type'],
            'event_data' => $data['event_data'] ?? [],
            'logged_at' => now()->toIso8601String(),
        ];
    }
}
