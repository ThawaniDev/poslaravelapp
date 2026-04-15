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
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
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
                    ->description(__('settings.email_config_description'))
                    ->schema([
                        Forms\Components\Select::make('email_provider')
                            ->label(__('settings.email_provider'))
                            ->options([
                                'smtp' => __('settings.email_provider_smtp'),
                                'mailtrap' => __('settings.email_provider_mailtrap'),
                                'mailgun' => __('settings.email_provider_mailgun'),
                                'ses' => __('settings.email_provider_ses'),
                                'postmark' => __('settings.email_provider_postmark'),
                                'resend' => __('settings.email_provider_resend'),
                            ])
                            ->default('smtp')
                            ->native(false)
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('email_host')
                            ->label(__('settings.email_host'))
                            ->placeholder('smtp.example.com')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('email_provider') === 'smtp'),
                        Forms\Components\TextInput::make('email_port')
                            ->label(__('settings.email_port'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(587)
                            ->visible(fn (Forms\Get $get) => $get('email_provider') === 'smtp'),
                        Forms\Components\Select::make('email_encryption')
                            ->label(__('settings.email_encryption'))
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                'none' => __('settings.no_encryption'),
                            ])
                            ->default('tls')
                            ->native(false)
                            ->visible(fn (Forms\Get $get) => $get('email_provider') === 'smtp'),
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
                        Forms\Components\TextInput::make('email_api_key')
                            ->label(__('settings.email_api_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->visible(fn (Forms\Get $get) => in_array($get('email_provider'), ['mailtrap', 'mailgun', 'ses', 'postmark', 'resend'])),
                        Forms\Components\TextInput::make('email_api_domain')
                            ->label(__('settings.email_api_domain'))
                            ->maxLength(255)
                            ->placeholder('mg.example.com')
                            ->visible(fn (Forms\Get $get) => $get('email_provider') === 'mailgun'),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.sender_configuration'))
                    ->schema([
                        Forms\Components\TextInput::make('email_from_address')
                            ->label(__('settings.from_address'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_from_name')
                            ->label(__('settings.from_name'))
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('email_reply_to')
                            ->label(__('settings.reply_to_address'))
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email_daily_limit')
                            ->label(__('settings.daily_send_limit'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText(__('settings.daily_limit_helper')),
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
        $data = $this->form->getState();
        $provider = $data['email_provider'] ?? 'smtp';

        try {
            if ($provider === 'smtp') {
                $host = $data['email_host'] ?? '';
                $port = (int) ($data['email_port'] ?? 587);
                $encryption = $data['email_encryption'] ?? 'tls';

                if (empty($host)) {
                    Notification::make()->title(__('settings.email_host_required'))->danger()->send();
                    return;
                }

                $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                $errno = 0;
                $errstr = '';
                $connection = @stream_socket_client(
                    ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
                    $errno,
                    $errstr,
                    10,
                    STREAM_CLIENT_CONNECT,
                    $context,
                );

                if ($connection) {
                    $response = fgets($connection, 512);
                    fclose($connection);

                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'test_email_connection',
                        entityType: 'system_setting',
                        entityId: 'email',
                        details: ['provider' => $provider, 'host' => $host, 'port' => $port, 'result' => 'success', 'response' => substr($response, 0, 100)],
                    );

                    Notification::make()
                        ->title(__('settings.connection_success'))
                        ->body(__('settings.smtp_server_responded', ['response' => trim(substr($response, 0, 50))]))
                        ->success()
                        ->send();
                } else {
                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'test_email_connection',
                        entityType: 'system_setting',
                        entityId: 'email',
                        details: ['provider' => $provider, 'host' => $host, 'port' => $port, 'result' => 'failed', 'error' => $errstr],
                    );

                    Notification::make()
                        ->title(__('settings.connection_failed'))
                        ->body($errstr ?: __('settings.could_not_connect'))
                        ->danger()
                        ->send();
                }
            } else {
                $apiKey = $data['email_api_key'] ?? '';
                if (empty($apiKey)) {
                    Notification::make()->title(__('settings.api_key_required'))->danger()->send();
                    return;
                }

                AdminActivityLog::record(
                    adminUserId: auth('admin')->id(),
                    action: 'test_email_connection',
                    entityType: 'system_setting',
                    entityId: 'email',
                    details: ['provider' => $provider, 'result' => 'api_key_present'],
                );

                Notification::make()
                    ->title(__('settings.api_key_configured'))
                    ->body(__('settings.api_provider_configured', ['provider' => ucfirst($provider)]))
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('settings.connection_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
