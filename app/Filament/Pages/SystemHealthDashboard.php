<?php

namespace App\Filament\Pages;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\Core\Models\FailedJob;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemHealthDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.system-health-dashboard';

    protected static ?string $pollingInterval = '30s';

    public static function getNavigationLabel(): string
    {
        return __('analytics.system_health');
    }

    public function getTitle(): string
    {
        return __('analytics.system_health_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'infrastructure.view']);
    }

    public function getViewData(): array
    {
        // Queue depth (pending jobs)
        $queueDepth = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;

        // Failed jobs count
        $failedJobs = FailedJob::count();
        $failedLast24h = FailedJob::where('failed_at', '>=', now()->subDay())->count();

        // Health check summary
        $healthChecks = SystemHealthCheck::latest('checked_at')
            ->get()
            ->unique('service');
        $healthyCount = $healthChecks->where('status', 'healthy')->count();
        $warningCount = $healthChecks->where('status', 'warning')->count();
        $criticalCount = $healthChecks->where('status', 'critical')->count();
        $avgLatency = round($healthChecks->avg('response_time_ms') ?? 0);

        // Top errors (from failed_jobs, last 7 days)
        $topErrors = FailedJob::where('failed_at', '>=', now()->subDays(7))
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return $payload['displayName'] ?? 'Unknown';
            })
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn ($count, $name) => ['job' => class_basename($name), 'count' => $count])
            ->values();

        // App update adoption
        $latestRelease = AppRelease::orderByDesc('released_at')->first();
        $updateStats = collect();
        if ($latestRelease) {
            $updateStats = AppUpdateStat::where('app_release_id', $latestRelease->id)
                ->selectRaw("status, COUNT(*) as count")
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn ($r) => [
                    ($r->status instanceof \BackedEnum ? $r->status->value : $r->status) => (int) $r->count,
                ]);
        }

        // Failed jobs trend (last 7 days)
        $failedTrend = FailedJob::where('failed_at', '>=', now()->subDays(7))
            ->selectRaw("DATE(failed_at) as date, COUNT(*) as count")
            ->groupBy(DB::raw('DATE(failed_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count]);

        return [
            'queueDepth' => $queueDepth,
            'failedJobs' => $failedJobs,
            'failedLast24h' => $failedLast24h,
            'healthyCount' => $healthyCount,
            'warningCount' => $warningCount,
            'criticalCount' => $criticalCount,
            'avgLatency' => $avgLatency,
            'topErrors' => $topErrors,
            'updateStats' => $updateStats,
            'latestRelease' => $latestRelease,
            'failedTrend' => $failedTrend,
            'healthChecks' => $healthChecks,
        ];
    }
}
