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
use Illuminate\Support\Facades\Http;

class WhatsAppSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
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
                    ->description(__('settings.whatsapp_config_description'))
                    ->schema([
                        Forms\Components\Select::make('whatsapp_provider')
                            ->label(__('settings.whatsapp_provider'))
                            ->options([
                                'meta_cloud_api' => __('settings.whatsapp_provider_meta_cloud_api'),
                                'twilio' => __('settings.whatsapp_provider_twilio'),
                                'messagebird' => __('settings.whatsapp_provider_messagebird'),
                            ])
                            ->default('meta_cloud_api')
                            ->native(false)
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('whatsapp_access_token')
                            ->label(__('settings.access_token'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('whatsapp_phone_number_id')
                            ->label(__('settings.phone_number_id'))
                            ->required()
                            ->maxLength(50)
                            ->visible(fn (Forms\Get $get) => $get('whatsapp_provider') === 'meta_cloud_api'),
                        Forms\Components\TextInput::make('whatsapp_business_account_id')
                            ->label(__('settings.business_account_id'))
                            ->required()
                            ->maxLength(50)
                            ->visible(fn (Forms\Get $get) => $get('whatsapp_provider') === 'meta_cloud_api'),
                        Forms\Components\TextInput::make('whatsapp_api_version')
                            ->label(__('settings.whatsapp_api_version'))
                            ->default('v18.0')
                            ->maxLength(10)
                            ->visible(fn (Forms\Get $get) => $get('whatsapp_provider') === 'meta_cloud_api'),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.webhook_config'))
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_webhook_verify_token')
                            ->label(__('settings.webhook_verify_token'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText(__('settings.webhook_token_helper')),
                        Forms\Components\TextInput::make('whatsapp_webhook_url')
                            ->label(__('settings.webhook_url'))
                            ->url()
                            ->maxLength(500)
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn () => url('/api/webhooks/whatsapp'))
                            ->helperText(__('settings.webhook_url_readonly')),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.whatsapp_templates'))
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_default_language')
                            ->label(__('settings.default_template_language'))
                            ->default('en')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('whatsapp_rate_limit')
                            ->label(__('settings.rate_limit_per_minute'))
                            ->numeric()
                            ->minValue(0)
                            ->default(80)
                            ->helperText(__('settings.whatsapp_rate_limit_helper')),
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

    public function testConnection(): void
    {
        $data = $this->form->getState();
        $provider = $data['whatsapp_provider'] ?? 'meta_cloud_api';
        $accessToken = $data['whatsapp_access_token'] ?? '';

        if (empty($accessToken)) {
            Notification::make()->title(__('settings.access_token_required'))->danger()->send();
            return;
        }

        try {
            if ($provider === 'meta_cloud_api') {
                $phoneNumberId = $data['whatsapp_phone_number_id'] ?? '';
                $apiVersion = $data['whatsapp_api_version'] ?? 'v18.0';

                if (empty($phoneNumberId)) {
                    Notification::make()->title(__('settings.phone_number_id_required'))->danger()->send();
                    return;
                }

                $response = Http::timeout(10)
                    ->withToken($accessToken)
                    ->get("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}");

                if ($response->successful()) {
                    $phoneData = $response->json();
                    $displayPhone = $phoneData['display_phone_number'] ?? $phoneNumberId;

                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'test_whatsapp_connection',
                        entityType: 'system_setting',
                        entityId: 'whatsapp',
                        details: ['provider' => $provider, 'phone' => $displayPhone, 'result' => 'success'],
                    );

                    Notification::make()
                        ->title(__('settings.connection_success'))
                        ->body(__('settings.whatsapp_phone_verified', ['phone' => $displayPhone]))
                        ->success()
                        ->send();
                } else {
                    $error = $response->json('error.message', __('settings.unknown_error'));

                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'test_whatsapp_connection',
                        entityType: 'system_setting',
                        entityId: 'whatsapp',
                        details: ['provider' => $provider, 'result' => 'failed', 'status' => $response->status()],
                    );

                    Notification::make()
                        ->title(__('settings.connection_failed'))
                        ->body($error)
                        ->danger()
                        ->send();
                }
            } else {
                // Generic: just verify token is non-empty
                AdminActivityLog::record(
                    adminUserId: auth('admin')->id(),
                    action: 'test_whatsapp_connection',
                    entityType: 'system_setting',
                    entityId: 'whatsapp',
                    details: ['provider' => $provider, 'result' => 'token_present'],
                );

                Notification::make()
                    ->title(__('settings.credentials_configured'))
                    ->body(__('settings.api_provider_configured', ['provider' => ucfirst(str_replace('_', ' ', $provider))]))
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
