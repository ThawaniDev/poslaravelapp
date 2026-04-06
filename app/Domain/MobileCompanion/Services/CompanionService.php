<?php

namespace App\Domain\MobileCompanion\Services;

use App\Domain\BackupSync\Models\SyncLog;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Order\Models\Order;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanionService
{
    /**
     * Real quick stats from database for mobile dashboard.
     */
    public function quickStats(string $storeId): array
    {
        $store = Store::find($storeId);
        $today = now()->startOfDay();

        // Today's revenue and transaction count from orders
        $todayOrders = Order::where('store_id', $storeId)
            ->where('created_at', '>=', $today)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        // Pending orders (new, confirmed, preparing)
        $pendingOrders = Order::where('store_id', $storeId)
            ->whereIn('status', ['new', 'confirmed', 'preparing'])
            ->count();

        // Active staff (clocked in today)
        $activeStaff = AttendanceRecord::where('store_id', $storeId)
            ->where('clock_in_at', '>=', $today)
            ->whereNull('clock_out_at')
            ->count();

        // Low stock items
        $lowStockItems = StockLevel::where('store_id', $storeId)
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->count();

        // Last sync
        $lastSync = SyncLog::where('store_id', $storeId)
            ->orderByDesc('started_at')
            ->first();

        return [
            'store_name' => $store?->name ?? 'Unknown',
            'today_revenue' => (float) ($todayOrders->revenue ?? 0),
            'today_transactions' => (int) ($todayOrders->count ?? 0),
            'today_orders' => (int) ($todayOrders->count ?? 0),
            'pending_orders' => $pendingOrders,
            'active_staff' => $activeStaff,
            'low_stock_items' => $lowStockItems,
            'last_sync' => $lastSync?->started_at?->toIso8601String(),
            'currency' => $store?->currency ?? 'SAR',
        ];
    }

    /**
     * Register a mobile session.
     */
    public function registerSession(string $storeId, string $userId, array $data): array
    {
        $sessionId = Str::uuid()->toString();

        // Store in cache/db — using cache for simplicity (sessions are ephemeral)
        $sessionData = [
            'session_id' => $sessionId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'device_name' => $data['device_name'] ?? 'Unknown',
            'device_os' => $data['device_os'] ?? 'Unknown',
            'app_version' => $data['app_version'] ?? '1.0.0',
            'started_at' => now()->toIso8601String(),
        ];

        cache()->put("companion_session:{$sessionId}", $sessionData, now()->addHours(24));

        // Track active sessions per store
        $activeSessions = cache()->get("companion_active_sessions:{$storeId}", []);
        $activeSessions[$sessionId] = $sessionData;
        cache()->put("companion_active_sessions:{$storeId}", $activeSessions, now()->addHours(24));

        return $sessionData;
    }

    /**
     * End a mobile session.
     */
    public function endSession(string $sessionId): array
    {
        $session = cache()->get("companion_session:{$sessionId}");
        $endedAt = now()->toIso8601String();

        if ($session) {
            $storeId = $session['store_id'];
            $activeSessions = cache()->get("companion_active_sessions:{$storeId}", []);
            unset($activeSessions[$sessionId]);
            cache()->put("companion_active_sessions:{$storeId}", $activeSessions, now()->addHours(24));
            cache()->forget("companion_session:{$sessionId}");
        }

        return [
            'session_id' => $sessionId,
            'ended_at' => $endedAt,
        ];
    }

    /**
     * List recent mobile sessions.
     */
    public function listSessions(string $storeId, int $limit = 20): array
    {
        $activeSessions = cache()->get("companion_active_sessions:{$storeId}", []);
        $sessions = array_values($activeSessions);

        return [
            'store_id' => $storeId,
            'sessions' => array_slice($sessions, 0, $limit),
            'total' => count($sessions),
        ];
    }

    /**
     * Get app preferences for a user.
     */
    public function getAppPreferences(string $userId): array
    {
        $prefs = cache()->get("companion_prefs:{$userId}", []);

        return array_merge([
            'user_id' => $userId,
            'theme' => 'system',
            'language' => 'en',
            'compact_mode' => false,
            'notifications_enabled' => true,
            'biometric_lock' => false,
            'default_page' => 'dashboard',
            'currency_display' => 'symbol',
        ], $prefs);
    }

    /**
     * Update app preferences.
     */
    public function updateAppPreferences(string $userId, array $data): array
    {
        $existing = cache()->get("companion_prefs:{$userId}", []);
        $updated = array_merge($existing, $data);
        cache()->put("companion_prefs:{$userId}", $updated, now()->addYear());

        return array_merge([
            'user_id' => $userId,
            'updated_at' => now()->toIso8601String(),
        ], $updated);
    }

    /**
     * Get quick actions configuration.
     */
    public function getQuickActions(string $storeId): array
    {
        $actions = cache()->get("companion_actions:{$storeId}");

        if (!$actions) {
            $actions = [
                ['id' => 'new_sale', 'label' => 'New Sale', 'icon' => 'shopping_cart', 'enabled' => true, 'order' => 1],
                ['id' => 'view_orders', 'label' => 'View Orders', 'icon' => 'receipt', 'enabled' => true, 'order' => 2],
                ['id' => 'add_product', 'label' => 'Add Product', 'icon' => 'add_box', 'enabled' => true, 'order' => 3],
                ['id' => 'staff_clock', 'label' => 'Staff Clock', 'icon' => 'access_time', 'enabled' => true, 'order' => 4],
                ['id' => 'end_of_day', 'label' => 'End of Day', 'icon' => 'nightlight', 'enabled' => false, 'order' => 5],
            ];
        }

        return [
            'store_id' => $storeId,
            'actions' => $actions,
        ];
    }

    /**
     * Update quick actions configuration.
     */
    public function updateQuickActions(string $storeId, array $data): array
    {
        $actions = $data['actions'] ?? [];
        cache()->put("companion_actions:{$storeId}", $actions, now()->addYear());

        return [
            'store_id' => $storeId,
            'actions' => $actions,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Extended mobile summary with real data.
     */
    public function getMobileSummary(string $storeId): array
    {
        $recentSyncs = SyncLog::where('store_id', $storeId)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        // Inventory alerts
        $alerts = [];
        $lowStock = StockLevel::where('store_id', $storeId)
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->with('product:id,name,name_ar')
            ->limit(5)
            ->get();

        foreach ($lowStock as $stock) {
            $alerts[] = [
                'type' => 'low_stock',
                'product_id' => $stock->product_id,
                'product_name' => $stock->product?->name ?? 'Unknown',
                'current_quantity' => (float) $stock->quantity,
                'reorder_point' => (float) $stock->reorder_point,
            ];
        }

        return [
            'store_id' => $storeId,
            'quick_stats' => $this->quickStats($storeId),
            'recent_syncs' => $recentSyncs->map(fn ($s) => [
                'id' => $s->id,
                'direction' => $s->direction->value,
                'status' => $s->status->value,
                'records_count' => $s->records_count,
            ])->toArray(),
            'alerts' => $alerts,
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
        $eventId = Str::uuid()->toString();

        // Store events in cache list for the store (last 100 events)
        $events = cache()->get("companion_events:{$storeId}", []);
        $event = [
            'event_id' => $eventId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'event_type' => $data['event_type'],
            'event_data' => $data['event_data'] ?? [],
            'logged_at' => now()->toIso8601String(),
        ];
        array_unshift($events, $event);
        $events = array_slice($events, 0, 100);
        cache()->put("companion_events:{$storeId}", $events, now()->addDays(30));

        return $event;
    }

    /**
     * Get mobile dashboard with aggregated data.
     */
    public function getDashboard(string $storeId): array
    {
        $store = Store::find($storeId);
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        // Today vs yesterday comparison
        $todayStats = Order::where('store_id', $storeId)
            ->where('created_at', '>=', $today)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $yesterdayStats = Order::where('store_id', $storeId)
            ->where('created_at', '>=', $yesterday)
            ->where('created_at', '<', $today)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $revenueChange = $yesterdayStats->revenue > 0
            ? round((($todayStats->revenue - $yesterdayStats->revenue) / $yesterdayStats->revenue) * 100, 1)
            : 0;

        return [
            'store' => [
                'id' => $store?->id,
                'name' => $store?->name,
                'currency' => $store?->currency ?? 'SAR',
                'is_active' => $store?->is_active ?? false,
            ],
            'today' => [
                'revenue' => (float) ($todayStats->revenue ?? 0),
                'orders' => (int) ($todayStats->count ?? 0),
                'average_order' => $todayStats->count > 0 ? round($todayStats->revenue / $todayStats->count, 2) : 0,
            ],
            'comparison' => [
                'yesterday_revenue' => (float) ($yesterdayStats->revenue ?? 0),
                'revenue_change_percent' => $revenueChange,
            ],
            'quick_stats' => $this->quickStats($storeId),
        ];
    }

    /**
     * Get branches for the organization.
     */
    public function getBranches(string $storeId): array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return ['branches' => []];
        }

        $branches = Store::where('organization_id', $store->organization_id)
            ->where('is_active', true)
            ->select('id', 'name', 'name_ar', 'branch_code', 'city', 'phone', 'is_main_branch', 'is_active')
            ->get();

        return [
            'current_store_id' => $storeId,
            'branches' => $branches->toArray(),
            'total' => $branches->count(),
        ];
    }

    /**
     * Get sales summary for date range.
     */
    public function getSalesSummary(string $storeId, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? \Carbon\Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $toDate = $to ? \Carbon\Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $orders = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $summary = $orders->clone()
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total), 0) as total_revenue, COALESCE(SUM(tax_amount), 0) as total_tax, COALESCE(SUM(discount_amount), 0) as total_discount, COALESCE(AVG(total), 0) as average_order')
            ->first();

        // Daily breakdown
        $dailyBreakdown = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->selectRaw("DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'period' => [
                'from' => $fromDate->toIso8601String(),
                'to' => $toDate->toIso8601String(),
            ],
            'summary' => [
                'total_orders' => (int) ($summary->total_orders ?? 0),
                'total_revenue' => (float) ($summary->total_revenue ?? 0),
                'total_tax' => (float) ($summary->total_tax ?? 0),
                'total_discount' => (float) ($summary->total_discount ?? 0),
                'average_order' => round((float) ($summary->average_order ?? 0), 2),
            ],
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Get active orders for the store.
     */
    public function getActiveOrders(string $storeId, int $limit = 20): array
    {
        $orders = Order::where('store_id', $storeId)
            ->whereIn('status', ['new', 'confirmed', 'preparing', 'ready'])
            ->with(['orderItems:id,order_id,product_id,quantity,unit_price', 'customer:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status->value ?? $o->status,
                'source' => $o->source->value ?? $o->source,
                'total' => (float) $o->total,
                'items_count' => $o->orderItems->count(),
                'customer' => $o->customer ? [
                    'name' => $o->customer->name,
                ] : null,
                'created_at' => $o->created_at?->toIso8601String(),
            ])->toArray(),
            'total' => $orders->count(),
        ];
    }

    /**
     * Get inventory alerts (low stock items).
     */
    public function getInventoryAlerts(string $storeId, int $limit = 30): array
    {
        $lowStock = StockLevel::where('store_id', $storeId)
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->with('product:id,name,name_ar,sku,image_url,sell_price')
            ->orderByRaw('quantity - reorder_point ASC')
            ->limit($limit)
            ->get();

        $outOfStock = StockLevel::where('store_id', $storeId)
            ->where('quantity', '<=', 0)
            ->with('product:id,name,name_ar,sku')
            ->count();

        return [
            'low_stock_items' => $lowStock->map(fn ($s) => [
                'product_id' => $s->product_id,
                'product_name' => $s->product?->name ?? 'Unknown',
                'product_name_ar' => $s->product?->name_ar,
                'sku' => $s->product?->sku,
                'image_url' => $s->product?->image_url,
                'current_quantity' => (float) $s->quantity,
                'reorder_point' => (float) $s->reorder_point,
                'deficit' => (float) ($s->reorder_point - $s->quantity),
            ])->toArray(),
            'total_low_stock' => $lowStock->count(),
            'total_out_of_stock' => $outOfStock,
        ];
    }

    /**
     * Get active staff members.
     */
    public function getActiveStaff(string $storeId): array
    {
        $today = now()->startOfDay();

        $staffUsers = StaffUser::where('store_id', $storeId)
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'photo_url', 'status')
            ->get();

        $clockedIn = AttendanceRecord::where('store_id', $storeId)
            ->where('clock_in_at', '>=', $today)
            ->whereNull('clock_out_at')
            ->pluck('staff_user_id')
            ->toArray();

        return [
            'staff' => $staffUsers->map(fn ($s) => [
                'id' => $s->id,
                'name' => trim($s->first_name . ' ' . $s->last_name),
                'email' => $s->email,
                'phone' => $s->phone,
                'photo_url' => $s->photo_url,
                'is_clocked_in' => in_array($s->id, $clockedIn),
                'status' => $s->status->value ?? $s->status,
            ])->toArray(),
            'total' => $staffUsers->count(),
            'clocked_in' => count($clockedIn),
        ];
    }

    /**
     * Toggle store availability (open/closed).
     */
    public function toggleStoreAvailability(string $storeId, bool $isActive): array
    {
        $store = Store::find($storeId);
        if ($store) {
            $store->update(['is_active' => $isActive]);
        }

        return [
            'store_id' => $storeId,
            'is_active' => $isActive,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
