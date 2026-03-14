<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Analytics\Models\FeatureAdoptionStat;
use App\Domain\Analytics\Models\PlatformDailyStat;
use App\Domain\Analytics\Models\PlatformPlanStat;
use App\Domain\Analytics\Models\StoreHealthSnapshot;
use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsReportingController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════
    // Main Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/dashboard
     * KPI cards + recent activity feed
     */
    public function mainDashboard(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $latestStat = PlatformDailyStat::orderByDesc('date')->first();

        $totalActiveStores = $latestStat?->total_active_stores ?? Store::count();
        $mrr = $latestStat?->total_mrr ?? 0;
        $newSignups = $latestStat?->new_registrations ?? 0;
        $churnCount = $latestStat?->churn_count ?? 0;
        $totalOrders = $latestStat?->total_orders ?? 0;
        $totalGmv = $latestStat?->total_gmv ?? 0;

        // Calculate churn rate
        $activeSubCount = StoreSubscription::where('status', 'active')->count();
        $churnRate = $activeSubCount > 0
            ? round(($churnCount / $activeSubCount) * 100, 2)
            : 0;

        // ZATCA compliance (from store_health_snapshots)
        $totalHealthStores = StoreHealthSnapshot::whereDate('date', $today)->count();
        $compliantStores = StoreHealthSnapshot::whereDate('date', $today)
            ->where('zatca_compliance', 1)->count();
        $zatcaComplianceRate = $totalHealthStores > 0
            ? round(($compliantStores / $totalHealthStores) * 100, 2)
            : 100;

        // Recent activity feed (last 20 events)
        $recentActivity = AdminActivityLog::orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'details' => $log->details,
                'created_at' => $log->created_at,
            ])
            ->toArray();

        return $this->success([
            'kpi' => [
                'total_active_stores' => $totalActiveStores,
                'mrr' => (float) $mrr,
                'new_signups_this_month' => $newSignups,
                'churn_rate' => $churnRate,
                'total_orders' => $totalOrders,
                'total_gmv' => (float) $totalGmv,
                'zatca_compliance_rate' => $zatcaComplianceRate,
            ],
            'recent_activity' => $recentActivity,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Revenue & Billing Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/revenue
     */
    public function revenueDashboard(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $planId = $request->query('plan_id');

        $dailyStats = PlatformDailyStat::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->get();

        // MRR & ARR from latest stat
        $latestStat = $dailyStats->last();
        $mrr = (float) ($latestStat?->total_mrr ?? 0);
        $arr = $mrr * 12;

        // Monthly revenue trend
        $revenueTrend = $dailyStats->map(fn ($s) => [
            'date' => $s->date->format('Y-m-d'),
            'mrr' => (float) $s->total_mrr,
            'gmv' => (float) $s->total_gmv,
        ])->toArray();

        // Revenue by plan
        $planStats = PlatformPlanStat::whereDate('date', $dateTo);
        if ($planId) {
            $planStats->where('subscription_plan_id', $planId);
        }
        $revenueByPlan = $planStats->get()->map(fn ($ps) => [
            'plan_id' => $ps->subscription_plan_id,
            'plan_name' => $ps->plan?->name ?? 'Unknown',
            'active_count' => $ps->active_stores,
            'mrr' => (float) $ps->revenue,
        ])->toArray();

        // Failed payments count
        $failedPaymentsCount = Invoice::where('status', 'failed')
            ->where('created_at', '>=', $dateFrom)
            ->where('created_at', '<', \Carbon\Carbon::parse($dateTo)->addDay()->toDateString())
            ->count();

        // Upcoming renewals (next 7 days)
        $upcomingRenewals = StoreSubscription::where('status', 'active')
            ->where('current_period_end', '>=', now())
            ->where('current_period_end', '<=', now()->addDays(7))
            ->count();

        return $this->success([
            'mrr' => $mrr,
            'arr' => $arr,
            'revenue_trend' => $revenueTrend,
            'revenue_by_plan' => $revenueByPlan,
            'failed_payments_count' => $failedPaymentsCount,
            'upcoming_renewals' => $upcomingRenewals,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Subscription Lifecycle Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/subscriptions
     */
    public function subscriptionDashboard(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        // Current subscription counts by status
        $statusRows = StoreSubscription::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $statusCounts = [];
        foreach ($statusRows as $row) {
            $key = $row->status instanceof \BackedEnum ? $row->status->value : $row->status;
            $statusCounts[$key] = (int) $row->count;
        }

        // Plan stats over time
        $planStatsOverTime = PlatformPlanStat::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($ps) => $ps->date->format('Y-m-d'))
            ->map(fn ($group, $date) => [
                'date' => $date,
                'active' => $group->sum('active_stores'),
                'trial' => $group->sum('trial_stores'),
                'churned' => $group->sum('churned_stores'),
            ])
            ->values()
            ->toArray();

        // Average subscription age (days)
        $avgAge = StoreSubscription::where('status', 'active')
            ->selectRaw('AVG(JULIANDAY(CURRENT_DATE) - JULIANDAY(created_at)) as avg_days')
            ->value('avg_days');

        // Total churn in period
        $totalChurn = PlatformPlanStat::whereBetween('date', [$dateFrom, $dateTo])
            ->sum('churned_stores');

        // Trial-to-paid conversion: trials that became active / total trials
        $totalTrials = StoreSubscription::where('status', 'trial')->count();
        $totalActive = StoreSubscription::where('status', 'active')->count();
        $conversionRate = ($totalTrials + $totalActive) > 0
            ? round(($totalActive / ($totalTrials + $totalActive)) * 100, 2)
            : 0;

        return $this->success([
            'status_counts' => $statusCounts,
            'lifecycle_trend' => $planStatsOverTime,
            'average_subscription_age_days' => round((float) ($avgAge ?? 0), 1),
            'total_churn_in_period' => (int) $totalChurn,
            'trial_to_paid_conversion_rate' => $conversionRate,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Store Performance Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/stores
     */
    public function storePerformanceDashboard(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);

        // Total stores + active stores
        $totalStores = Store::count();
        $activeStores = Store::where('is_active', true)->count();

        // Top stores by some metric (use store_health_snapshots or basic info)
        $topStores = Store::where('is_active', true)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name ?? $s->store_name ?? 'Store #' . $s->id,
                'is_active' => $s->is_active,
            ])
            ->toArray();

        // Store health summary
        $today = now()->toDateString();
        $healthStats = StoreHealthSnapshot::whereDate('date', $today)
            ->selectRaw("sync_status, COUNT(*) as count")
            ->groupBy('sync_status')
            ->get()
            ->pluck('count', 'sync_status')
            ->toArray();

        return $this->success([
            'total_stores' => $totalStores,
            'active_stores' => $activeStores,
            'top_stores' => $topStores,
            'health_summary' => $healthStats,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Feature Adoption Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/features
     */
    public function featureAdoptionDashboard(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        // Latest feature adoption stats
        $latestDate = FeatureAdoptionStat::whereBetween('date', [$dateFrom, $dateTo])
            ->max('date');

        $features = [];
        if ($latestDate) {
            $totalStores = Store::count();
            $features = FeatureAdoptionStat::whereDate('date', $latestDate)
                ->orderByDesc('stores_using')
                ->get()
                ->map(fn ($f) => [
                    'feature_key' => $f->feature_key,
                    'stores_using' => $f->stores_using,
                    'total_eligible' => $f->total_eligible,
                    'adoption_percentage' => $totalStores > 0
                        ? round(($f->stores_using / $totalStores) * 100, 2)
                        : 0,
                ])
                ->toArray();
        }

        // Trend data (total usage events per day)
        $trend = FeatureAdoptionStat::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('date, SUM(stores_using) as total_stores, SUM(total_eligible) as total_eligible')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date instanceof \Carbon\Carbon ? $row->date->format('Y-m-d') : (string) $row->date,
                'total_stores' => (int) $row->total_stores,
                'total_eligible' => (int) $row->total_eligible,
            ])
            ->toArray();

        return $this->success([
            'features' => $features,
            'trend' => $trend,
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Support Analytics Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/support
     */
    public function supportAnalyticsDashboard(Request $request): JsonResponse
    {
        // Support metrics from activity logs (as a proxy since support_tickets may not exist yet)
        $supportActions = AdminActivityLog::where('entity_type', 'support')
            ->count();

        return $this->success([
            'total_support_actions' => $supportActions,
            'open_tickets' => 0,
            'avg_first_response_hours' => 0,
            'avg_resolution_hours' => 0,
            'sla_compliance_rate' => 100,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // System Health Dashboard
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/health
     */
    public function systemHealthDashboard(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        // Store health from store_health_snapshots
        $totalSnapshots = StoreHealthSnapshot::whereDate('date', $today)->count();
        $errorSnapshots = StoreHealthSnapshot::whereDate('date', $today)
            ->where('error_count', '>', 0)->count();
        $totalErrors = StoreHealthSnapshot::whereDate('date', $today)
            ->sum('error_count');

        $syncStatuses = StoreHealthSnapshot::whereDate('date', $today)
            ->selectRaw('sync_status, COUNT(*) as count')
            ->groupBy('sync_status')
            ->get()
            ->pluck('count', 'sync_status')
            ->toArray();

        return $this->success([
            'stores_monitored' => $totalSnapshots,
            'stores_with_errors' => $errorSnapshots,
            'total_errors_today' => (int) $totalErrors,
            'sync_status_breakdown' => $syncStatuses,
            'api_error_rate' => 0,
            'queue_depth' => 0,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Notification Analytics
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/notifications
     */
    public function notificationAnalytics(Request $request): JsonResponse
    {
        return $this->success([
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_opened' => 0,
            'delivery_rate' => 100,
            'open_rate' => 0,
            'by_channel' => [],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Daily Stats (CRUD-like access for the pre-aggregated tables)
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/analytics/daily-stats
     */
    public function listDailyStats(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $stats = PlatformDailyStat::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date->format('Y-m-d'),
                'total_active_stores' => $s->total_active_stores,
                'new_registrations' => $s->new_registrations,
                'total_orders' => $s->total_orders,
                'total_gmv' => (float) $s->total_gmv,
                'total_mrr' => (float) $s->total_mrr,
                'churn_count' => $s->churn_count,
            ])
            ->toArray();

        return $this->success($stats);
    }

    /**
     * GET /admin/analytics/plan-stats
     */
    public function listPlanStats(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $stats = PlatformPlanStat::with('plan')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->get()
            ->map(fn ($ps) => [
                'id' => $ps->id,
                'plan_id' => $ps->subscription_plan_id,
                'plan_name' => $ps->plan?->name ?? 'Unknown',
                'date' => $ps->date->format('Y-m-d'),
                'active_stores' => $ps->active_stores,
                'trial_stores' => $ps->trial_stores,
                'churned_stores' => $ps->churned_stores,
                'revenue' => (float) $ps->revenue,
            ])
            ->toArray();

        return $this->success($stats);
    }

    /**
     * GET /admin/analytics/feature-stats
     */
    public function listFeatureStats(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $stats = FeatureAdoptionStat::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->orderBy('feature_key')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'feature_key' => $f->feature_key,
                'date' => $f->date->format('Y-m-d'),
                'stores_using' => $f->stores_using,
                'total_eligible' => $f->total_eligible,
            ])
            ->toArray();

        return $this->success($stats);
    }

    /**
     * GET /admin/analytics/store-health
     */
    public function listStoreHealth(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $syncStatus = $request->query('sync_status');

        $query = StoreHealthSnapshot::with('store')->whereDate('date', $date);
        if ($syncStatus) {
            $query->where('sync_status', $syncStatus);
        }

        $snapshots = $query->get()->map(fn ($sh) => [
            'id' => $sh->id,
            'store_id' => $sh->store_id,
            'store_name' => $sh->store?->name ?? $sh->store?->store_name ?? 'Store #' . $sh->store_id,
            'date' => $sh->date->format('Y-m-d'),
            'sync_status' => $sh->sync_status,
            'zatca_compliance' => $sh->zatca_compliance,
            'error_count' => $sh->error_count,
            'last_activity_at' => $sh->last_activity_at,
        ])->toArray();

        return $this->success($snapshots);
    }

    // ═══════════════════════════════════════════════════════════
    // Export Endpoints
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /admin/analytics/export/revenue
     */
    public function exportRevenue(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $stats = PlatformDailyStat::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->get();

        $this->logActivity($request, 'export_revenue', "Exported revenue report ({$dateFrom} to {$dateTo})");

        return $this->success([
            'export_type' => 'revenue',
            'format' => $request->input('format', 'xlsx'),
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'record_count' => $stats->count(),
            'download_url' => null, // Placeholder for actual file generation
        ]);
    }

    /**
     * POST /admin/analytics/export/subscriptions
     */
    public function exportSubscriptions(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $count = StoreSubscription::where('created_at', '>=', $dateFrom)
            ->where('created_at', '<', \Carbon\Carbon::parse($dateTo)->addDay()->toDateString())
            ->count();

        $this->logActivity($request, 'export_subscriptions', "Exported subscription report ({$dateFrom} to {$dateTo})");

        return $this->success([
            'export_type' => 'subscriptions',
            'format' => $request->input('format', 'xlsx'),
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'record_count' => $count,
            'download_url' => null,
        ]);
    }

    /**
     * POST /admin/analytics/export/stores
     */
    public function exportStores(Request $request): JsonResponse
    {
        $count = Store::count();

        $this->logActivity($request, 'export_stores', "Exported store performance report (total: {$count})");

        return $this->success([
            'export_type' => 'stores',
            'format' => $request->input('format', 'xlsx'),
            'record_count' => $count,
            'download_url' => null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════

    private function logActivity(Request $request, string $action, string $description): void
    {
        $admin = $request->user('admin-api');
        if ($admin) {
            AdminActivityLog::forceCreate([
                'admin_user_id' => $admin->id,
                'action' => $action,
                'entity_type' => 'analytics',
                'entity_id' => null,
                'details' => json_encode(['description' => $description]),
                'ip_address' => $request->ip(),
                'created_at' => now()->toDateTimeString(),
            ]);
        }
    }
}
