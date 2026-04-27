<?php

namespace App\Filament\Pages;

use App\Domain\Core\Models\Store;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Services\ThawaniService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ThawaniStoreConnectionPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.thawani-store-connection';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_thawani');
    }

    public static function getNavigationLabel(): string
    {
        return __('thawani.store_connection');
    }

    public function getTitle(): string
    {
        return __('thawani.store_connection');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['thawani.manage_config', 'thawani.menu']);
    }

    public ?string $selectedStoreId = null;
    public ?array $data = [];

    public function mount(): void
    {
        // Auto-select the first store if only one exists
        $stores = Store::orderBy('name')->get();
        if ($stores->count() === 1) {
            $this->selectedStoreId = $stores->first()->id;
            $this->loadStoreConfig();
        }
    }

    public function updatedSelectedStoreId(): void
    {
        $this->loadStoreConfig();
    }

    protected function loadStoreConfig(): void
    {
        if (!$this->selectedStoreId) {
            $this->form->fill([]);
            return;
        }

        $config = ThawaniStoreConfig::where('store_id', $this->selectedStoreId)->first();

        $this->form->fill([
            'thawani_store_id' => $config?->thawani_store_id ?? '',
            'marketplace_url' => $config?->marketplace_url ?? '',
            'api_key' => $config?->api_key ?? '',
            'api_secret' => '', // Never pre-fill secret for security
            'is_connected' => $config?->is_connected ?? false,
            'auto_sync_products' => $config?->auto_sync_products ?? false,
            'auto_sync_inventory' => $config?->auto_sync_inventory ?? false,
            'auto_accept_orders' => $config?->auto_accept_orders ?? false,
            'commission_rate' => $config?->commission_rate ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('thawani.api_credentials'))
                    ->description(__('thawani.api_credentials_description'))
                    ->schema([
                        Forms\Components\TextInput::make('marketplace_url')
                            ->label(__('thawani.marketplace_url'))
                            ->placeholder('https://thawaniapp.com')
                            ->url()
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('thawani_store_id')
                            ->label(__('thawani.thawani_store_id'))
                            ->placeholder('TH-STORE-001')
                            ->maxLength(255)
                            ->helperText(__('thawani.thawani_store_id_help')),
                        Forms\Components\TextInput::make('api_key')
                            ->label(__('thawani.api_key'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('api_secret')
                            ->label(__('thawani.api_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText(__('thawani.api_secret_help')),
                    ])->columns(2),

                Forms\Components\Section::make(__('thawani.sync_preferences'))
                    ->schema([
                        Forms\Components\Toggle::make('auto_sync_products')
                            ->label(__('thawani.auto_sync_products'))
                            ->default(false),
                        Forms\Components\Toggle::make('auto_sync_inventory')
                            ->label(__('thawani.auto_sync_inventory'))
                            ->default(false),
                        Forms\Components\Toggle::make('auto_accept_orders')
                            ->label(__('thawani.auto_accept_orders'))
                            ->default(false),
                        Forms\Components\TextInput::make('commission_rate')
                            ->label(__('thawani.commission_rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function getStoresProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Store::orderBy('name')->get();
    }

    public function save(): void
    {
        if (!$this->selectedStoreId) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        $formData = $this->form->getState();

        $service = app(ThawaniService::class);
        $service->saveConfig($this->selectedStoreId, [
            'thawani_store_id' => $formData['thawani_store_id'] ?? null,
            'marketplace_url' => $formData['marketplace_url'] ?? null,
            'api_key' => $formData['api_key'] ?? null,
            'api_secret' => !empty($formData['api_secret']) ? $formData['api_secret'] : null,
            'is_connected' => $formData['is_connected'] ?? false,
            'auto_sync_products' => $formData['auto_sync_products'] ?? false,
            'auto_sync_inventory' => $formData['auto_sync_inventory'] ?? false,
            'auto_accept_orders' => $formData['auto_accept_orders'] ?? false,
            'commission_rate' => $formData['commission_rate'] ?? null,
        ]);

        Notification::make()
            ->title(__('thawani.config_saved'))
            ->success()
            ->send();

        // Reload to show updated state
        $this->loadStoreConfig();
    }

    public function testConnection(): void
    {
        if (!$this->selectedStoreId) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        // Save first so credentials are stored before testing
        $this->save();

        $service = app(ThawaniService::class);
        $result = $service->testConnection($this->selectedStoreId);

        if ($result['success']) {
            Notification::make()
                ->title(__('thawani.connection_success'))
                ->body($result['message'] ?? '')
                ->success()
                ->send();

            // Reload to show updated connection status
            $this->loadStoreConfig();
        } else {
            Notification::make()
                ->title(__('thawani.connection_failed'))
                ->body($result['message'] ?? 'Unknown error')
                ->danger()
                ->send();
        }
    }

    public function disconnect(): void
    {
        if (!$this->selectedStoreId) {
            Notification::make()->title(__('thawani.select_store_first'))->warning()->send();
            return;
        }

        $service = app(ThawaniService::class);
        $service->disconnect($this->selectedStoreId);

        Notification::make()
            ->title(__('thawani.store_disconnected'))
            ->success()
            ->send();

        $this->loadStoreConfig();
    }

    public function getViewData(): array
    {
        $config = null;
        if ($this->selectedStoreId) {
            $config = ThawaniStoreConfig::where('store_id', $this->selectedStoreId)->first();
        }

        return [
            'stores' => $this->stores,
            'currentConfig' => $config,
        ];
    }
}
