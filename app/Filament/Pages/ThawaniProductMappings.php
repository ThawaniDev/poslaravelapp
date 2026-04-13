<?php

namespace App\Filament\Pages;

use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Services\ThawaniService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class ThawaniProductMappings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.thawani-product-mappings';

    public ?string $selectedStore = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_integrations');
    }

    public static function getNavigationLabel(): string
    {
        return __('thawani.product_mappings');
    }

    public function getTitle(): string
    {
        return __('thawani.product_mappings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['thawani.menu', 'thawani.manage_config']);
    }

    public function pushProducts(): void
    {
        if (!$this->selectedStore) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        $service = app(ThawaniService::class);
        $result = $service->pushProductsToThawani($this->selectedStore);

        if ($result['success']) {
            Notification::make()->title(__('thawani.products_pushed'))->success()->send();
        } else {
            Notification::make()->title($result['message'])->danger()->send();
        }
    }

    public function pullProducts(): void
    {
        if (!$this->selectedStore) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        $service = app(ThawaniService::class);
        $result = $service->pullProductsFromThawani($this->selectedStore);

        if ($result['success']) {
            Notification::make()->title(__('thawani.products_pulled'))->success()->send();
        } else {
            Notification::make()->title($result['message'])->danger()->send();
        }
    }

    public function getViewData(): array
    {
        $stores = ThawaniStoreConfig::where('is_connected', true)->with('store')->get();

        $mappings = collect();
        if ($this->selectedStore) {
            $mappings = ThawaniProductMapping::where('store_id', $this->selectedStore)
                ->with('product')
                ->orderByDesc('last_synced_at')
                ->get();
        }

        return [
            'stores' => $stores,
            'mappings' => $mappings,
            'selectedStore' => $this->selectedStore,
        ];
    }
}
