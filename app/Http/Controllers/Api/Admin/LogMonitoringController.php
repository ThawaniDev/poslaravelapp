<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\PlatformEventLog;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use App\Domain\Notification\Models\NotificationEventLog;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use App\Domain\Security\Models\SecurityAlert;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogMonitoringController extends BaseApiController
{
    // ─── Log Stats ───────────────────────────────────────────

    /**
     * GET /admin/logs/stats
     * Aggregate statistics for all log types.
     */
    public function stats(): JsonResponse
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $last24h = $now->copy()->subDay();

        // Activity logs
        $totalActivity = AdminActivityLog::count();
        $activityToday = AdminActivityLog::where('created_at', '>=', $now->startOfDay())->count();
        $activityThisMonth = AdminActivityLog::where('created_at', '>=', $startOfMonth)->count();
        $topActions = AdminActivityLog::where('created_at', '>=', $startOfMonth)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'action');

        // Security alerts
        $totalAlerts = SecurityAlert::count();
        $unresolvedAlerts = SecurityAlert::where('status', '!=', 'resolved')->count();
        $criticalAlerts = SecurityAlert::where('severity', 'critical')
            ->where('status', '!=', 'resolved')->count();
        $alertsLast24h = SecurityAlert::where('created_at', '>=', $last24h)->count();

        // Notification logs
        $totalNotifications = NotificationEventLog::count();
        $notificationsToday = NotificationEventLog::where('sent_at', '>=', $now->startOfDay())->count();
        $failedNotifications = NotificationEventLog::where('status', 'failed')->count();
        $channelBreakdown = NotificationEventLog::where('sent_at', '>=', $startOfMonth)
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        return $this->success([
            'activity' => [
                'total' => $totalActivity,
                'today' => $activityToday,
                'this_month' => $activityThisMonth,
                'top_actions' => $topActions,
            ],
            'security' => [
                'total' => $totalAlerts,
                'unresolved' => $unresolvedAlerts,
                'critical' => $criticalAlerts,
                'last_24h' => $alertsLast24h,
            ],
            'notifications' => [
                'total' => $totalNotifications,
                'today' => $notificationsToday,
                'failed' => $failedNotifications,
                'channel_breakdown' => $channelBreakdown,
            ],
        ], 'Log stats retrieved');
    }

    // ─── Admin Activity Logs ─────────────────────────────────

    public function listActivityLogs(Request $request): JsonResponse
    {
        $query = AdminActivityLog::query()->orderByDesc('created_at');

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', $request->admin_user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('action', 'like', "%{$s}%")
                  ->orWhere('entity_type', 'like', "%{$s}%")
                  ->orWhere('ip_address', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        return $this->success($logs, 'Activity logs retrieved');
    }

    public function showActivityLog(string $id): JsonResponse
    {
        $log = AdminActivityLog::find($id);

        if (!$log) {
            return $this->notFound('Activity log not found');
        }

        return $this->success($log, 'Activity log details');
    }

    // ─── Security Alerts ─────────────────────────────────────

    public function listSecurityAlerts(Request $request): JsonResponse
    {
        $query = SecurityAlert::query()->orderByDesc('created_at');

        if ($request->filled('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('alert_type', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $alerts = $query->paginate($request->integer('per_page', 20));

        return $this->success($alerts, 'Security alerts retrieved');
    }

    public function showSecurityAlert(string $id): JsonResponse
    {
        $alert = SecurityAlert::find($id);

        if (!$alert) {
            return $this->notFound('Security alert not found');
        }

        return $this->success($alert, 'Security alert details');
    }

    public function resolveSecurityAlert(Request $request, string $id): JsonResponse
    {
        $alert = SecurityAlert::find($id);

        if (!$alert) {
            return $this->notFound('Security alert not found');
        }

        if ($alert->status === 'resolved' || ($alert->status instanceof \App\Domain\Security\Enums\SecurityAlertStatus && $alert->status->value === 'resolved')) {
            return $this->error('Alert is already resolved', 422);
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes', ''),
        ]);

        return $this->success($alert->fresh(), 'Security alert resolved');
    }

    // ─── Notification Logs ───────────────────────────────────

    public function listNotificationLogs(Request $request): JsonResponse
    {
        $query = NotificationEventLog::query()->orderByDesc('sent_at');

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('channel', 'like', "%{$s}%")
                  ->orWhere('error_message', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        return $this->success($logs, 'Notification logs retrieved');
    }

    // ─── Platform Events ─────────────────────────────────────

    public function listPlatformEvents(Request $request): JsonResponse
    {
        $query = PlatformEventLog::query()->orderByDesc('created_at');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('message', 'like', "%{$s}%")
                  ->orWhere('source', 'like', "%{$s}%")
                  ->orWhere('event_type', 'like', "%{$s}%");
            });
        }

        $events = $query->paginate($request->integer('per_page', 20));

        return $this->success($events, 'Platform events retrieved');
    }

    public function createPlatformEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|max:50',
            'message'    => 'required|string',
            'level'      => 'sometimes|string|in:debug,info,warning,error,critical',
            'source'     => 'sometimes|string|max:100',
            'details'    => 'sometimes|array',
        ]);

        $event = PlatformEventLog::create([
            'event_type'    => $request->event_type,
            'message'       => $request->message,
            'level'         => $request->input('level', 'info'),
            'source'        => $request->input('source'),
            'details'       => $request->input('details'),
            'admin_user_id' => $request->user()->id,
            'created_at'    => now(),
        ]);

        return $this->created($event, 'Platform event logged');
    }

    public function showPlatformEvent(string $id): JsonResponse
    {
        $event = PlatformEventLog::find($id);

        if (!$event) {
            return $this->notFound('Platform event not found');
        }

        return $this->success($event, 'Platform event details');
    }

    // ─── System Health ───────────────────────────────────────

    public function healthDashboard(Request $request): JsonResponse
    {
        $checks = SystemHealthCheck::query()
            ->selectRaw('service, status, MAX(checked_at) as last_checked, AVG(response_time_ms) as avg_response_ms')
            ->groupBy('service', 'status')
            ->orderBy('service')
            ->get();

        $totalChecks = SystemHealthCheck::count();
        $healthyCount = SystemHealthCheck::where('status', 'healthy')->count();
        $degradedCount = SystemHealthCheck::where('status', 'degraded')->count();
        $downCount = SystemHealthCheck::where('status', 'down')->count();

        $latestByService = SystemHealthCheck::query()
            ->orderByDesc('checked_at')
            ->get()
            ->unique('service')
            ->values();

        return $this->success([
            'summary' => [
                'total_checks'  => $totalChecks,
                'healthy'       => $healthyCount,
                'degraded'      => $degradedCount,
                'down'          => $downCount,
                'health_score'  => $totalChecks > 0 ? round(($healthyCount / $totalChecks) * 100, 1) : 100,
            ],
            'services'   => $latestByService,
            'breakdown'  => $checks,
        ], 'Health dashboard retrieved');
    }

    public function listHealthChecks(Request $request): JsonResponse
    {
        $query = SystemHealthCheck::query()->orderByDesc('checked_at');

        if ($request->filled('service')) {
            $query->where('service', $request->service);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $checks = $query->paginate($request->integer('per_page', 20));

        return $this->success($checks, 'Health checks retrieved');
    }

    public function createHealthCheck(Request $request): JsonResponse
    {
        $request->validate([
            'service'          => 'required|string|max:50|in:api,database,queue,cache,storage,mail',
            'status'           => 'required|string|in:healthy,degraded,down',
            'response_time_ms' => 'sometimes|integer|min:0',
            'details'          => 'sometimes|array',
        ]);

        $check = SystemHealthCheck::create([
            'service'          => $request->service,
            'status'           => $request->status,
            'response_time_ms' => $request->input('response_time_ms'),
            'details'          => $request->input('details'),
            'checked_at'       => now(),
        ]);

        return $this->created($check, 'Health check recorded');
    }

    // ─── Store Health ────────────────────────────────────────

    public function listStoreHealth(Request $request): JsonResponse
    {
        $query = StoreHealthSnapshot::query()->orderByDesc('date');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->sync_status);
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('zatca_compliance')) {
            $query->where('zatca_compliance', filter_var($request->zatca_compliance, FILTER_VALIDATE_BOOLEAN));
        }

        $snapshots = $query->paginate($request->integer('per_page', 20));

        return $this->success($snapshots, 'Store health snapshots retrieved');
    }
}
