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

class WhatsAppSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 15;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.whatsapp_settings');
    }

    public function getTitle(): string
    {
        return __('settings.whatsapp_business_api');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::where('group', 'whatsapp')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.whatsapp_configuration'))
                    ->schema([
                        Forms\Components\Select::make('whatsapp_provider')
                            ->label(__('settings.whatsapp_provider'))
                            ->options([
                                'meta_cloud_api' => 'Meta Cloud API',
                                'twilio' => 'Twilio',
                            ])
                            ->default('meta_cloud_api')
                            ->native(false),
                        Forms\Components\TextInput::make('whatsapp_access_token')
                            ->label(__('settings.access_token'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('whatsapp_phone_number_id')
                            ->label(__('settings.phone_number_id'))
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp_business_account_id')
                            ->label(__('settings.business_account_id'))
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp_webhook_verify_token')
                            ->label(__('settings.webhook_verify_token'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
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
                ['value' => $value, 'group' => 'whatsapp', 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_whatsapp_settings',
            entityType: 'system_setting',
            entityId: 'whatsapp',
            details: ['provider' => $data['whatsapp_provider'] ?? null],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }
}
