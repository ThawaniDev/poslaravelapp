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
                    ->schema([
                        Forms\Components\TextInput::make('push_fcm_server_key')
                            ->label(__('settings.fcm_server_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('push_fcm_project_id')
                            ->label(__('settings.fcm_project_id'))
                            ->maxLength(100),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.apns_settings'))
                    ->schema([
                        Forms\Components\TextInput::make('push_apns_key_id')
                            ->label(__('settings.apns_key_id'))
                            ->maxLength(20),
                        Forms\Components\TextInput::make('push_apns_team_id')
                            ->label(__('settings.apns_team_id'))
                            ->maxLength(20),
                        Forms\Components\FileUpload::make('push_apns_key_file')
                            ->label(__('settings.apns_key_file'))
                            ->acceptedFileTypes(['.p8', 'application/pkcs8'])
                            ->maxSize(10)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'push_apns_key_file' && is_array($value)) {
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
}
