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

class PushNotificationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
    protected static ?int $navigationSort = 14;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.push_notification');
    }

    public function getTitle(): string
    {
        return __('settings.push_notification_settings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::where('group', 'push')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.fcm_settings'))
                    ->description(__('settings.fcm_settings_description'))
                    ->schema([
                        Forms\Components\TextInput::make('push_fcm_server_key')
                            ->label(__('settings.fcm_server_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->helperText(__('settings.fcm_server_key_helper')),
                        Forms\Components\TextInput::make('push_fcm_project_id')
                            ->label(__('settings.fcm_project_id'))
                            ->maxLength(100)
                            ->required(),
                        Forms\Components\Textarea::make('push_fcm_service_account_json')
                            ->label(__('settings.fcm_service_account'))
                            ->helperText(__('settings.fcm_service_account_helper'))
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.apns_settings'))
                    ->description(__('settings.apns_settings_description'))
                    ->schema([
                        Forms\Components\TextInput::make('push_apns_key_id')
                            ->label(__('settings.apns_key_id'))
                            ->maxLength(20),
                        Forms\Components\TextInput::make('push_apns_team_id')
                            ->label(__('settings.apns_team_id'))
                            ->maxLength(20),
                        Forms\Components\TextInput::make('push_apns_bundle_id')
                            ->label(__('settings.apns_bundle_id'))
                            ->maxLength(255)
                            ->placeholder('com.thawani.pos'),
                        Forms\Components\Select::make('push_apns_environment')
                            ->label(__('settings.apns_environment'))
                            ->options([
                                'production' => __('settings.production'),
                                'sandbox' => __('settings.sandbox'),
                            ])
                            ->default('production')
                            ->native(false),
                        Forms\Components\FileUpload::make('push_apns_key_file')
                            ->label(__('settings.apns_key_file'))
                            ->acceptedFileTypes(['.p8', 'application/pkcs8'])
                            ->maxSize(10)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.push_defaults'))
                    ->schema([
                        Forms\Components\TextInput::make('push_default_ttl_seconds')
                            ->label(__('settings.default_ttl'))
                            ->numeric()
                            ->default(86400)
                            ->suffix(__('settings.seconds'))
                            ->helperText(__('settings.ttl_helper')),
                        Forms\Components\Toggle::make('push_collapse_enabled')
                            ->label(__('settings.collapse_notifications'))
                            ->default(true)
                            ->helperText(__('settings.collapse_helper')),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'push_apns_key_file') {
                // Store uploaded .p8 file contents as base64 in settings
                if (is_array($value) && ! empty($value)) {
                    $filePath = collect($value)->first();
                    if ($filePath && \Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                        $contents = \Illuminate\Support\Facades\Storage::disk('local')->get($filePath);
                        SystemSetting::updateOrCreate(
                            ['key' => 'push_apns_key_contents'],
                            ['value' => base64_encode($contents), 'group' => 'push', 'updated_by' => auth('admin')->id()],
                        );
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);
                    }
                }
                continue;
            }
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'push', 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_push_settings',
            entityType: 'system_setting',
            entityId: 'push',
            details: ['keys_updated' => array_keys($data)],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }

    public function testConnection(): void
    {
        $data = $this->form->getState();
        $projectId = $data['push_fcm_project_id'] ?? '';
        $serverKey = $data['push_fcm_server_key'] ?? '';

        if (empty($projectId) && empty($serverKey)) {
            Notification::make()
                ->title(__('settings.fcm_credentials_required'))
                ->danger()
                ->send();
            return;
        }

        try {
            // Validate FCM server key by calling the FCM info endpoint
            if (! empty($serverKey)) {
                $response = Http::timeout(10)
                    ->withHeaders(['Authorization' => "key={$serverKey}"])
                    ->post('https://fcm.googleapis.com/fcm/send', [
                        'dry_run' => true,
                        'registration_ids' => ['test_token_validation'],
                    ]);

                $body = $response->json();
                // A 200 with MismatchSenderId or InvalidRegistration means the key is valid but the token is fake
                if ($response->successful()) {
                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'test_push_connection',
                        entityType: 'system_setting',
                        entityId: 'push',
                        details: ['project_id' => $projectId, 'result' => 'success'],
                    );

                    Notification::make()
                        ->title(__('settings.connection_success'))
                        ->body(__('settings.fcm_key_valid'))
                        ->success()
                        ->send();
                    return;
                }

                if ($response->status() === 401) {
                    Notification::make()
                        ->title(__('settings.connection_failed'))
                        ->body(__('settings.fcm_key_invalid'))
                        ->danger()
                        ->send();
                    return;
                }
            }

            AdminActivityLog::record(
                adminUserId: auth('admin')->id(),
                action: 'test_push_connection',
                entityType: 'system_setting',
                entityId: 'push',
                details: ['project_id' => $projectId, 'result' => 'configured'],
            );

            Notification::make()
                ->title(__('settings.credentials_configured'))
                ->body(__('settings.push_credentials_saved'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('settings.connection_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
