<?php

namespace App\Filament\Pages;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\BackupSync\Enums\AppReleasePlatform;
use App\Domain\BackupSync\Enums\AppUpdateStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * App Update Dashboard
 *
 * KPI overview page showing:
 *  - Total, active, and inactive releases
 *  - Per-platform active versions
 *  - Adoption funnel (pending → downloading → downloaded → installed → failed)
 *  - Auto-rollback candidates
 *  - Recent failure log
 */
class AppUpdateDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.app-update-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_updates');
    }

    public static function getNavigationLabel(): string
    {
        return __('updates.dashboard');
    }

    public function getTitle(): string
    {
        return __('updates.update_dashboard_title');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['app_updates.view', 'app_updates.manage']);
    }

    public function getViewData(): array
    {
        $totalReleases = AppRelease::count();
        $activeReleases = AppRelease::where('is_active', true)->count();

        // Per-platform latest active release
        $latestByPlatform = [];
        foreach (AppReleasePlatform::cases() as $platform) {
            $latestByPlatform[$platform->value] = AppRelease::where('is_active', true)
                ->where('platform', $platform)
                ->latest('released_at')
                ->first();
        }

        // Adoption funnel — stats for last 30 days
        $since = now()->subDays(30);
        $statsBase = AppUpdateStat::where('updated_at', '>=', $since);

        $funnelCounts = collect(AppUpdateStatus::cases())->mapWithKeys(
            fn ($status) => [$status->value => (clone $statsBase)->where('status', $status)->count()]
        )->toArray();

        $totalStats = array_sum($funnelCounts);
        $installationRate = $totalStats > 0
            ? round(($funnelCounts[AppUpdateStatus::Installed->value] / $totalStats) * 100, 1)
            : 0;

        // Auto-rollback candidates: active releases with failure rate ≥ 10%
        $rollbackCandidates = AppRelease::where('is_active', true)
            ->where('released_at', '>=', now()->subDays(1))
            ->get()
            ->filter(function ($release) {
                $total = AppUpdateStat::where('app_release_id', $release->id)->count();
                if ($total < 10) {
                    return false;
                }
                $failed = AppUpdateStat::where('app_release_id', $release->id)
                    ->where('status', AppUpdateStatus::Failed)
                    ->count();

                return ($failed / $total * 100) >= 10;
            });

        // Recent failures
        $recentFailures = AppUpdateStat::with('appRelease')
            ->where('status', AppUpdateStatus::Failed)
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return compact(
            'totalReleases',
            'activeReleases',
            'latestByPlatform',
            'funnelCounts',
            'totalStats',
            'installationRate',
            'rollbackCandidates',
            'recentFailures',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_release')
                ->label(__('updates.add_release'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => \App\Filament\Resources\AppReleaseResource::getUrl('create'))
                ->visible(fn () => auth('admin')->user()?->hasAnyPermission(['app_updates.manage'])),

            Action::make('view_stats')
                ->label(__('updates.view_all_stats'))
                ->icon('heroicon-o-chart-bar')
                ->url(fn () => \App\Filament\Resources\AppUpdateStatResource::getUrl('index')),
        ];
    }
}
