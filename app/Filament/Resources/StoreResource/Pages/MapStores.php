<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Domain\Core\Models\Store;
use App\Filament\Resources\StoreResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class MapStores extends Page
{
    protected static string $resource = StoreResource::class;

    protected static string $view = 'filament.resources.store-resource.pages.map-stores';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public function getTitle(): string
    {
        return __('admin_dashboard.store_map_title');
    }

    protected function getViewData(): array
    {
        $stores = Store::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('organization')
            ->select([
                'id', 'name', 'name_ar', 'city', 'address',
                'latitude', 'longitude', 'is_active', 'business_type',
                'phone', 'email', 'organization_id', 'created_at',
            ])
            ->get();

        $markers = $stores->map(fn (Store $store) => [
            'id' => $store->id,
            'name' => $store->name,
            'name_ar' => $store->name_ar,
            'city' => $store->city ?? __('admin_dashboard.store_map_unknown'),
            'address' => $store->address,
            'lat' => (float) $store->latitude,
            'lng' => (float) $store->longitude,
            'is_active' => $store->is_active,
            'business_type' => $store->business_type?->value ?? $store->business_type ?? '-',
            'phone' => $store->phone,
            'email' => $store->email,
            'organization' => $store->organization?->name ?? '-',
            'view_url' => StoreResource::getUrl('view', ['record' => $store]),
            'created_at' => $store->created_at?->format('M j, Y'),
        ])->values()->toArray();

        $totalStores = Store::count();
        $mappedStores = $stores->count();
        $unmappedStores = $totalStores - $mappedStores;
        $activeStores = $stores->where('is_active', true)->count();
        $inactiveStores = $stores->where('is_active', false)->count();

        $cityCounts = $stores->groupBy('city')->map->count()->sortDesc()->take(10);

        $businessTypeCounts = $stores
            ->groupBy(fn ($s) => $s->business_type?->value ?? $s->business_type ?? 'other')
            ->map->count()
            ->sortDesc();

        return [
            'markers' => $markers,
            'totalStores' => $totalStores,
            'mappedStores' => $mappedStores,
            'unmappedStores' => $unmappedStores,
            'activeStores' => $activeStores,
            'inactiveStores' => $inactiveStores,
            'cityCounts' => $cityCounts,
            'businessTypeCounts' => $businessTypeCounts,
        ];
    }
}
