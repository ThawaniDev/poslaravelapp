<?php

namespace App\Filament\Pages;

use App\Domain\Core\Models\Store;
use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class StorePerformanceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.store-performance-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('analytics.store_performance');
    }

    public function getTitle(): string
    {
        return __('analytics.store_performance_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'analytics.stores']);
    }

    public function getViewData(): array
    {
        $totalStores = Store::count();
        $activeStores = Store::where('is_active', true)->count();
        $inactiveStores = $totalStores - $activeStores;

        $today = now()->toDateString();

        // Health summary
        $healthSummary = StoreHealthSnapshot::whereDate('date', $today)
            ->selectRaw('sync_status, COUNT(*) as count')
            ->groupBy('sync_status')
            ->get()
            ->pluck('count', 'sync_status');

        // ZATCA compliance
        $totalMonitored = StoreHealthSnapshot::whereDate('date', $today)->count();
        $zatcaCompliant = StoreHealthSnapshot::whereDate('date', $today)
            ->where('zatca_compliance', true)->count();
        $zatcaRate = $totalMonitored > 0
            ? round(($zatcaCompliant / $totalMonitored) * 100, 1)
            : 100;

        // Stores with errors today
        $storesWithErrors = StoreHealthSnapshot::whereDate('date', $today)
            ->where('error_count', '>', 0)
            ->count();

        // New stores this month
        $newThisMonth = Store::where('created_at', '>=', now()->startOfMonth())->count();

        // Top 20 stores by GMV (MTD)
        $topStores = DB::table('transactions')
            ->join('stores', 'transactions.store_id', '=', 'stores.id')
            ->where('transactions.status', 'completed')
            ->where('transactions.created_at', '>=', now()->startOfMonth())
            ->selectRaw('stores.name, stores.city, COUNT(*) as order_count, SUM(transactions.total_amount) as total_gmv')
            ->groupBy('stores.id', 'stores.name', 'stores.city')
            ->orderByDesc('total_gmv')
            ->limit(20)
            ->get();

        // Stores by city (top 10)
        $storesByCity = Store::whereNotNull('city')
            ->where('city', '!=', '')
            ->selectRaw('city, COUNT(*) as count')
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['city' => $r->city, 'count' => (int) $r->count]);

        // Business type breakdown
        $businessTypes = Store::whereNotNull('business_type')
            ->selectRaw('business_type, COUNT(*) as count')
            ->groupBy('business_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->business_type instanceof \BackedEnum ? $r->business_type->value : $r->business_type,
                'count' => (int) $r->count,
            ]);

        return [
            'totalStores' => $totalStores,
            'activeStores' => $activeStores,
            'inactiveStores' => $inactiveStores,
            'healthSummary' => $healthSummary,
            'zatcaRate' => $zatcaRate,
            'storesWithErrors' => $storesWithErrors,
            'totalMonitored' => $totalMonitored,
            'newThisMonth' => $newThisMonth,
            'topStores' => $topStores,
            'storesByCity' => $storesByCity,
            'businessTypes' => $businessTypes,
        ];
    }
}
