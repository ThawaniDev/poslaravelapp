<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\ThawaniMarketplaceConfig;
use App\Domain\ThawaniIntegration\Enums\ThawaniConnectionStatus;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ThawaniMarketplaceConfigPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.thawani-marketplace-config';

    public ?array $data = [];

    protected ?ThawaniMarketplaceConfig $cachedConfig = null;

    public static function getNavigationLabel(): string
    {
        return __('settings.thawani_marketplace');
    }

    public function getTitle(): string
    {
        return __('settings.thawani_marketplace_config');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $config = $this->getConfig();
        $this->form->fill($config ? $config->toArray() : []);
    }

    protected function getConfig(): ?ThawaniMarketplaceConfig
    {
        return $this->cachedConfig ??= ThawaniMarketplaceConfig::first();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.api_credentials'))
                    ->schema([
                        Forms\Components\TextInput::make('client_id_encrypted')
                            ->label(__('settings.client_id'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('client_secret_encrypted')
                            ->label(__('settings.client_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('api_base_url')
                            ->label(__('settings.api_base_url'))
                            ->url()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('api_version')
                            ->label(__('settings.api_version'))
                            ->maxLength(20)
                            ->placeholder('v1'),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.webhook_config'))
                    ->schema([
                        Forms\Components\TextInput::make('redirect_url')
                            ->label(__('settings.redirect_url'))
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('webhook_url')
                            ->label(__('settings.webhook_url'))
                            ->url()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('webhook_secret_encrypted')
                            ->label(__('settings.webhook_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.sync_settings'))
                    ->schema([
                        Forms\Components\TextInput::make('sync_interval_minutes')
                            ->label(__('settings.sync_interval'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(60)
                            ->suffix(__('settings.minutes')),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('settings.is_active'))
                            ->default(false),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.connection_status'))
                    ->schema([
                        Forms\Components\Placeholder::make('connection_status_display')
                            ->label(__('settings.status'))
                            ->content(fn () => $this->getConfig()?->connection_status?->value ?? 'unknown'),
                        Forms\Components\Placeholder::make('last_connection_display')
                            ->label(__('settings.last_connection'))
                            ->content(fn () => $this->getConfig()?->last_connection_at?->diffForHumans() ?? __('settings.never')),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $config = $this->getConfig();
        if ($config) {
            $config->update($data);
        } else {
            ThawaniMarketplaceConfig::create($data);
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_thawani_config',
            entityType: 'thawani_marketplace_config',
            entityId: $config?->id ?? 'new',
            details: ['is_active' => $data['is_active'] ?? false],
        );

        Notification::make()
            ->title(__('settings.config_saved'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label(__('settings.save'))
                ->submit('save'),
        ];
    }
}
