<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ZatcaConfigurationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_zatca');
    }
    protected static ?int $navigationSort = 11;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.zatca_configuration');
    }

    public function getTitle(): string
    {
        return __('settings.zatca_configuration');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::where('group', 'zatca')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.zatca_environment'))
                    ->schema([
                        Forms\Components\Select::make('zatca_environment')
                            ->label(__('settings.environment'))
                            ->options([
                                'sandbox' => __('settings.sandbox'),
                                'production' => __('settings.production'),
                            ])
                            ->default('sandbox')
                            ->live()
                            ->native(false),
                        Forms\Components\TextInput::make('zatca_api_base_url')
                            ->label(__('settings.api_base_url'))
                            ->url()
                            ->maxLength(500)
                            ->helperText(__('settings.zatca_url_helper')),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.zatca_credentials'))
                    ->schema([
                        Forms\Components\TextInput::make('zatca_client_id')
                            ->label(__('settings.client_id'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('zatca_client_secret')
                            ->label(__('settings.client_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('zatca_certificate_path')
                            ->label(__('settings.certificate_path'))
                            ->maxLength(500)
                            ->helperText(__('settings.certificate_path_helper')),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'zatca', 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_zatca_settings',
            entityType: 'system_setting',
            entityId: 'zatca',
            details: ['keys_updated' => array_keys($data)],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }

    public function testConnection(): void
    {
        Notification::make()
            ->title(__('settings.connection_test_sent'))
            ->info()
            ->send();
    }
}
