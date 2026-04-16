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

class SmsProviderSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
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
                    ->description(__('settings.sms_config_description'))
                    ->schema([
                        Forms\Components\Select::make('sms_provider')
                            ->label(__('settings.sms_provider'))
                            ->options([
                                'unifonic' => __('settings.sms_provider_unifonic'),
                                'taqnyat' => __('settings.sms_provider_taqnyat'),
                                'msegat' => __('settings.sms_provider_msegat'),
                                'twilio' => __('settings.sms_provider_twilio'),
                            ])
                            ->default('unifonic')
                            ->native(false)
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('sms_api_key')
                            ->label(__('settings.sms_api_key'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('sms_api_secret')
                            ->label(__('settings.sms_api_secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->visible(fn (Forms\Get $get) => in_array($get('sms_provider'), ['twilio'])),
                        Forms\Components\TextInput::make('sms_sender_name')
                            ->label(__('settings.sender_name'))
                            ->required()
                            ->maxLength(20)
                            ->helperText(__('settings.sender_name_helper')),
                        Forms\Components\TextInput::make('sms_base_url')
                            ->label(__('settings.api_base_url'))
                            ->url()
                            ->maxLength(500)
                            ->helperText(__('settings.sms_base_url_helper')),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.sms_limits'))
                    ->schema([
                        Forms\Components\TextInput::make('sms_rate_limit_per_minute')
                            ->label(__('settings.rate_limit_per_minute'))
                            ->numeric()
                            ->minValue(0)
                            ->default(60),
                        Forms\Components\TextInput::make('sms_daily_limit')
                            ->label(__('settings.daily_send_limit'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText(__('settings.daily_limit_helper')),
                        Forms\Components\TextInput::make('sms_cost_per_message')
                            ->label(__('settings.cost_per_message'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001)
                            ->prefix('SAR'),
                    ])->columns(3),
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
        $data = $this->form->getState();
        $provider = $data['sms_provider'] ?? 'unifonic';
        $apiKey = $data['sms_api_key'] ?? '';
        $baseUrl = $data['sms_base_url'] ?? '';

        if (empty($apiKey)) {
            Notification::make()->title(__('settings.api_key_required'))->danger()->send();
            return;
        }

        try {
            $result = match ($provider) {
                'unifonic' => $this->testUnifonicConnection($apiKey, $baseUrl),
                'taqnyat' => $this->testTaqnyatConnection($apiKey, $baseUrl),
                'msegat' => $this->testMsegatConnection($apiKey, $baseUrl),
                'twilio' => $this->testTwilioConnection($apiKey, $data['sms_api_secret'] ?? '', $baseUrl),
                default => ['success' => false, 'message' => __('settings.unsupported_provider')],
            };

            AdminActivityLog::record(
                adminUserId: auth('admin')->id(),
                action: 'test_sms_connection',
                entityType: 'system_setting',
                entityId: 'sms',
                details: ['provider' => $provider, 'result' => $result['success'] ? 'success' : 'failed'],
            );

            if ($result['success']) {
                Notification::make()
                    ->title(__('settings.connection_success'))
                    ->body($result['message'] ?? '')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title(__('settings.connection_failed'))
                    ->body($result['message'] ?? '')
                    ->danger()
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

    private function testUnifonicConnection(string $apiKey, string $baseUrl): array
    {
        $url = rtrim($baseUrl ?: 'https://el.cloud.unifonic.com', '/') . '/rest/SMS/messages';
        $response = Http::timeout(10)->withHeaders(['Authorization' => "Bearer {$apiKey}"])->get($url);

        return [
            'success' => $response->status() !== 401 && $response->status() !== 403,
            'message' => __('settings.sms_api_responded', ['status' => $response->status()]),
        ];
    }

    private function testTaqnyatConnection(string $apiKey, string $baseUrl): array
    {
        $url = rtrim($baseUrl ?: 'https://api.taqnyat.sa', '/') . '/system/credit/balance';
        $response = Http::timeout(10)->withHeaders(['Authorization' => "Bearer {$apiKey}"])->get($url);

        return [
            'success' => $response->successful(),
            'message' => $response->successful()
                ? __('settings.sms_balance_check_ok')
                : __('settings.sms_api_responded', ['status' => $response->status()]),
        ];
    }

    private function testMsegatConnection(string $apiKey, string $baseUrl): array
    {
        $url = rtrim($baseUrl ?: 'https://www.msegat.com/gw', '/') . '/Credits.php';
        $response = Http::timeout(10)->post($url, ['apiKey' => $apiKey, 'userName' => 'check']);

        return [
            'success' => $response->status() !== 401,
            'message' => __('settings.sms_api_responded', ['status' => $response->status()]),
        ];
    }

    private function testTwilioConnection(string $accountSid, string $authToken, string $baseUrl): array
    {
        if (empty($authToken)) {
            return ['success' => false, 'message' => __('settings.api_secret_required')];
        }
        $url = rtrim($baseUrl ?: 'https://api.twilio.com', '/') . "/2010-04-01/Accounts/{$accountSid}.json";
        $response = Http::timeout(10)->withBasicAuth($accountSid, $authToken)->get($url);

        return [
            'success' => $response->successful(),
            'message' => $response->successful()
                ? __('settings.sms_account_verified')
                : __('settings.sms_api_responded', ['status' => $response->status()]),
        ];
    }
}
