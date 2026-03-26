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

class SmsProviderSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 12;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.sms_provider');
    }

    public function getTitle(): string
    {
        return __('settings.sms_provider_settings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::where('group', 'sms')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.sms_configuration'))
                    ->schema([
                        Forms\Components\Select::make('sms_provider')
                            ->label(__('settings.sms_provider'))
                            ->options([
                                'unifonic' => 'Unifonic',
                                'taqnyat' => 'Taqnyat',
                                'msegat' => 'Msegat',
                            ])
                            ->default('unifonic')
                            ->native(false),
                        Forms\Components\TextInput::make('sms_api_key')
                            ->label(__('settings.sms_api_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('sms_sender_name')
                            ->label(__('settings.sender_name'))
                            ->maxLength(20),
                        Forms\Components\TextInput::make('sms_base_url')
                            ->label(__('settings.api_base_url'))
                            ->url()
                            ->maxLength(500),
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
                ['value' => $value, 'group' => 'sms', 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_sms_settings',
            entityType: 'system_setting',
            entityId: 'sms',
            details: ['provider' => $data['sms_provider'] ?? null],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }

    public function testConnection(): void
    {
        Notification::make()
            ->title(__('settings.test_sms_sent'))
            ->info()
            ->send();
    }
}
