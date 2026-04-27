<?php

namespace App\Filament\Pages;

use App\Domain\ThawaniIntegration\Models\ThawaniCategoryMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncLog;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncQueue;
use Filament\Pages\Page;

class ThawaniSyncDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.thawani-sync-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_thawani');
    }

    public static function getNavigationLabel(): string
    {
        return __('thawani.sync_dashboard');
    }

    public function getTitle(): string
    {
        return __('thawani.sync_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['thawani.view_dashboard', 'thawani.menu']);
    }

    public function getViewData(): array
    {
        $connectedStores = ThawaniStoreConfig::where('is_connected', true)->with('store')->get();
        $totalMappedProducts = ThawaniProductMapping::count();
        $totalMappedCategories = ThawaniCategoryMapping::count();
        $pendingQueue = ThawaniSyncQueue::where('status', 'pending')->count();
        $failedQueue = ThawaniSyncQueue::where('status', 'failed')->count();

        $syncLogsToday = ThawaniSyncLog::whereDate('created_at', today())->count();
        $failedSyncsToday = ThawaniSyncLog::whereDate('created_at', today())->where('status', 'failed')->count();
        $successSyncsToday = ThawaniSyncLog::whereDate('created_at', today())->where('status', 'success')->count();

        $recentLogs = ThawaniSyncLog::with('store')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $storeStats = [];
        foreach ($connectedStores as $config) {
            $storeStats[] = [
                'config' => $config,
                'products' => ThawaniProductMapping::where('store_id', $config->store_id)->count(),
                'categories' => ThawaniCategoryMapping::where('store_id', $config->store_id)->count(),
                'pending' => ThawaniSyncQueue::where('store_id', $config->store_id)->where('status', 'pending')->count(),
            ];
        }

        return [
            'connectedStores' => $connectedStores,
            'totalMappedProducts' => $totalMappedProducts,
            'totalMappedCategories' => $totalMappedCategories,
            'pendingQueue' => $pendingQueue,
            'failedQueue' => $failedQueue,
            'syncLogsToday' => $syncLogsToday,
            'failedSyncsToday' => $failedSyncsToday,
            'successSyncsToday' => $successSyncsToday,
            'recentLogs' => $recentLogs,
            'storeStats' => $storeStats,
        ];
    }
}
