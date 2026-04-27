<?php

namespace App\Filament\Pages;

use App\Domain\ThawaniIntegration\Models\ThawaniCategoryMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Services\ThawaniService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ThawaniCategoryMappings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.thawani-category-mappings';

    public ?string $selectedStore = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_thawani');
    }

    public static function getNavigationLabel(): string
    {
        return __('thawani.category_mappings');
    }

    public function getTitle(): string
    {
        return __('thawani.category_mappings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['thawani.menu', 'thawani.manage_config']);
    }

    public function pushCategories(): void
    {
        if (!$this->selectedStore) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        $service = app(ThawaniService::class);
        $result = $service->pushCategoriesToThawani($this->selectedStore);

        if ($result['success']) {
            Notification::make()->title(__('thawani.categories_pushed'))->success()->send();
        } else {
            Notification::make()->title($result['message'])->danger()->send();
        }
    }

    public function pullCategories(): void
    {
        if (!$this->selectedStore) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        $service = app(ThawaniService::class);
        $result = $service->pullCategoriesFromThawani($this->selectedStore);

        if ($result['success']) {
            Notification::make()->title(__('thawani.categories_pulled'))->success()->send();
        } else {
            Notification::make()->title($result['message'])->danger()->send();
        }
    }

    public function getViewData(): array
    {
        $stores = ThawaniStoreConfig::where('is_connected', true)->with('store')->get();

        $mappings = collect();
        if ($this->selectedStore) {
            $mappings = ThawaniCategoryMapping::where('store_id', $this->selectedStore)
                ->with('category')
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
