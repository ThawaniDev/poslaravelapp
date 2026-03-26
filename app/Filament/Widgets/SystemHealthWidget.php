<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class SystemHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function getHeading(): ?string
    {
        return __('admin_dashboard.system_health');
    }

    public static function canView(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission([
            'infrastructure.view', 'infrastructure.manage',
            'settings.view', 'settings.feature_flags',
        ]);
    }

    protected function getStats(): array
    {
        $data = Cache::remember('filament:system_health', 60, function () {
            $failedJobs = DB::table('failed_jobs')->count();

            $totalFlags = DB::table('feature_flags')->count();
            $activeFlags = DB::table('feature_flags')->where('is_enabled', true)->count();

            $latestRelease = DB::table('app_releases')
                ->where('is_active', true)
                ->orderByDesc('released_at')
                ->first();

            $latestVersion = $latestRelease->version_number ?? '—';

            $pendingUpdates = 0;
            if ($latestRelease) {
                $pendingUpdates = DB::table('app_update_stats')
                    ->where('app_release_id', '!=', $latestRelease->id)
                    ->distinct('store_id')
                    ->count('store_id');
            }

            $zatcaCompliant = DB::table('stores')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('zatca_certificates')
                      ->whereColumn('zatca_certificates.store_id', 'stores.id')
                      ->where('zatca_certificates.status', 'active');
                })
                ->count();

            $totalActiveStores = DB::table('stores')->where('is_active', true)->count();

            $activeAnnouncements = DB::table('platform_announcements')
                ->where('display_start_at', '<=', now())
                ->where('display_end_at', '>=', now())
                ->count();

            return compact(
                'failedJobs', 'totalFlags', 'activeFlags', 'latestVersion',
                'pendingUpdates', 'zatcaCompliant', 'totalActiveStores', 'activeAnnouncements'
            );
        });

        $zatcaPercent = $data['totalActiveStores'] > 0
            ? round(($data['zatcaCompliant'] / $data['totalActiveStores']) * 100)
            : 0;

        return [
            Stat::make(__('admin_dashboard.failed_jobs'), Number::format($data['failedJobs']))
                ->description(__('admin_dashboard.queue_failures'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($data['failedJobs'] > 0 ? 'danger' : 'success'),

            Stat::make(__('admin_dashboard.feature_flags_active'), $data['activeFlags'] . ' / ' . $data['totalFlags'])
                ->description(__('admin_dashboard.of_total_flags', ['active' => $data['activeFlags'], 'total' => $data['totalFlags']]))
                ->descriptionIcon('heroicon-m-flag')
                ->color('info'),

            Stat::make(__('admin_dashboard.latest_app_release'), $data['latestVersion'])
                ->description(__('admin_dashboard.current_version'))
                ->descriptionIcon('heroicon-m-rocket-launch')
                ->color('primary'),

            Stat::make(__('admin_dashboard.pending_updates'), Number::format($data['pendingUpdates']))
                ->description(__('admin_dashboard.stores_not_on_latest'))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color($data['pendingUpdates'] > 0 ? 'warning' : 'success'),

            Stat::make(__('admin_dashboard.zatca_compliant'), $data['zatcaCompliant'] . ' (' . $zatcaPercent . '%)')
                ->description(__('admin_dashboard.stores_with_zatca'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($zatcaPercent >= 80 ? 'success' : ($zatcaPercent >= 50 ? 'warning' : 'danger')),

            Stat::make(__('admin_dashboard.active_announcements'), Number::format($data['activeAnnouncements']))
                ->description(__('admin_dashboard.currently_displayed'))
                ->descriptionIcon('heroicon-m-megaphone')
                ->color($data['activeAnnouncements'] > 0 ? 'info' : 'gray'),
        ];
    }
}
