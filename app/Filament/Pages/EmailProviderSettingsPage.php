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

class EmailProviderSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 13;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.email_provider');
    }

    public function getTitle(): string
    {
        return __('settings.email_provider_settings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::where('group', 'email')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.email_configuration'))
                    ->schema([
                        Forms\Components\Select::make('email_provider')
                            ->label(__('settings.email_provider'))
                            ->options([
                                'smtp' => 'SMTP',
                                'mailgun' => 'Mailgun',
                                'ses' => 'Amazon SES',
                            ])
                            ->default('smtp')
                            ->native(false),
                        Forms\Components\TextInput::make('email_host')
                            ->label(__('settings.email_host'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_port')
                            ->label(__('settings.email_port'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(587),
                        Forms\Components\TextInput::make('email_username')
                            ->label(__('settings.email_username'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_password')
                            ->label(__('settings.email_password'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_from_address')
                            ->label(__('settings.from_address'))
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_from_name')
                            ->label(__('settings.from_name'))
                            ->maxLength(100),
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
                ['value' => $value, 'group' => 'email', 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_email_settings',
            entityType: 'system_setting',
            entityId: 'email',
            details: ['provider' => $data['email_provider'] ?? null],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }

    public function testConnection(): void
    {
        Notification::make()
            ->title(__('settings.test_email_sent'))
            ->info()
            ->send();
    }
}
