<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\Core\Models\FailedJob;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
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

    public static function getNavigationBadge(): ?string
    {
        $critical = SystemHealthCheck::latest('checked_at')
            ->get()
            ->unique('service')
            ->where('status', 'critical')
            ->count();

        return $critical > 0 ? (string) $critical : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'infrastructure.view']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_health_checks')
                ->label(__('analytics.run_health_checks'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    $this->runAllHealthChecks();
                }),
            Actions\Action::make('flush_cache')
                ->label(__('infrastructure.flush_cache'))
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => auth('admin')->user()?->hasPermissionTo('infrastructure.manage'))
                ->action(function () {
                    Cache::flush();
                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'flush_cache',
                        entityType: 'system',
                        entityId: 'cache',
                    );
                    Notification::make()->title(__('infrastructure.cache_flushed'))->success()->send();
                }),
        ];
    }

    public function runAllHealthChecks(): void
    {
        $services = [
            'database' => fn () => $this->checkDatabase(),
            'cache' => fn () => $this->checkCache(),
            'queue' => fn () => $this->checkQueue(),
            'storage' => fn () => $this->checkStorage(),
        ];

        $results = [];
        foreach ($services as $service => $checker) {
            $start = microtime(true);
            try {
                $result = $checker();
                $responseTime = (int) ((microtime(true) - $start) * 1000);

                SystemHealthCheck::create([
                    'service' => $service,
                    'status' => $result['status'],
                    'response_time_ms' => $responseTime,
                    'details' => $result['details'] ?? null,
                    'error_message' => $result['error'] ?? null,
                    'triggered_by' => auth('admin')->id(),
                    'checked_at' => now(),
                ]);
                $results[$service] = $result['status'];
            } catch (\Throwable $e) {
                $responseTime = (int) ((microtime(true) - $start) * 1000);
                SystemHealthCheck::create([
                    'service' => $service,
                    'status' => 'critical',
                    'response_time_ms' => $responseTime,
                    'error_message' => $e->getMessage(),
                    'triggered_by' => auth('admin')->id(),
                    'checked_at' => now(),
                ]);
                $results[$service] = 'critical';
            }
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'run_health_checks',
            entityType: 'system_health',
            entityId: 'manual',
            details: $results,
        );

        $criticalCount = collect($results)->where(fn ($s) => $s === 'critical')->count();
        if ($criticalCount > 0) {
            Notification::make()
                ->title(__('analytics.health_checks_completed'))
                ->body(__('analytics.critical_issues_found', ['count' => $criticalCount]))
                ->danger()
                ->send();
        } else {
            Notification::make()
                ->title(__('analytics.health_checks_completed'))
                ->body(__('analytics.all_services_healthy'))
                ->success()
                ->send();
        }
    }

    private function checkDatabase(): array
    {
        DB::select('SELECT 1');
        $size = DB::select("SELECT pg_database_size(current_database()) as size")[0]->size ?? 0;
        return [
            'status' => 'healthy',
            'details' => ['db_size_mb' => round($size / 1048576, 2)],
        ];
    }

    private function checkCache(): array
    {
        $key = 'health_check_' . uniqid();
        Cache::put($key, 'ok', 10);
        $value = Cache::get($key);
        Cache::forget($key);

        return [
            'status' => $value === 'ok' ? 'healthy' : 'critical',
            'details' => ['driver' => config('cache.default')],
            'error' => $value !== 'ok' ? 'Cache read/write failed' : null,
        ];
    }

    private function checkQueue(): array
    {
        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedRecent = FailedJob::where('failed_at', '>=', now()->subHour())->count();

        $status = 'healthy';
        if ($failedRecent > 10 || $pendingJobs > 1000) {
            $status = 'critical';
        } elseif ($failedRecent > 0 || $pendingJobs > 100) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'details' => ['pending_jobs' => $pendingJobs, 'failed_last_hour' => $failedRecent],
        ];
    }

    private function checkStorage(): array
    {
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);
        $usedPercent = $totalBytes > 0 ? round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1) : 0;

        $status = 'healthy';
        if ($usedPercent > 95) {
            $status = 'critical';
        } elseif ($usedPercent > 85) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'details' => [
                'free_gb' => round($freeBytes / 1073741824, 2),
                'total_gb' => round($totalBytes / 1073741824, 2),
                'used_percent' => $usedPercent,
            ],
        ];
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

        // Last health check time
        $lastCheckAt = SystemHealthCheck::max('checked_at');

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
            'lastCheckAt' => $lastCheckAt,
        ];
    }
}
