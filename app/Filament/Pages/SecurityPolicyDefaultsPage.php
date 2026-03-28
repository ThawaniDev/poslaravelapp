<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\SecurityPolicyDefault;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SecurityPolicyDefaultsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
    protected static ?int $navigationSort = 17;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.security_policies');
    }

    public function getTitle(): string
    {
        return __('settings.security_policy_defaults');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.edit', 'settings.security_policies']);
    }

    public function mount(): void
    {
        $policy = SecurityPolicyDefault::first();
        $this->form->fill($policy ? $policy->toArray() : []);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.session_management'))
                    ->schema([
                        Forms\Components\TextInput::make('session_timeout_minutes')
                            ->label(__('settings.session_timeout'))
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(480)
                            ->default(30)
                            ->suffix(__('settings.minutes'))
                            ->helperText(__('settings.session_timeout_helper')),
                        Forms\Components\Toggle::make('require_reauth_on_wake')
                            ->label(__('settings.require_reauth_on_wake'))
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.pin_password'))
                    ->schema([
                        Forms\Components\TextInput::make('pin_min_length')
                            ->label(__('settings.pin_min_length'))
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(8)
                            ->default(4),
                        Forms\Components\Select::make('pin_complexity')
                            ->label(__('settings.pin_complexity'))
                            ->options([
                                'numeric_only' => __('settings.numeric_only'),
                                'alphanumeric' => __('settings.alphanumeric'),
                                'alphanumeric_with_special' => __('settings.alphanumeric_with_special'),
                            ])
                            ->default('numeric_only')
                            ->native(false),
                        Forms\Components\Toggle::make('require_unique_pins')
                            ->label(__('settings.require_unique_pins'))
                            ->default(true)
                            ->helperText(__('settings.require_unique_pins_helper')),
                        Forms\Components\TextInput::make('pin_expiry_days')
                            ->label(__('settings.pin_expiry_days'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix(__('settings.days'))
                            ->helperText(__('settings.pin_expiry_helper')),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.biometric'))
                    ->schema([
                        Forms\Components\Toggle::make('biometric_enabled_default')
                            ->label(__('settings.biometric_enabled'))
                            ->default(true)
                            ->helperText(__('settings.biometric_enabled_helper')),
                        Forms\Components\Toggle::make('biometric_can_replace_pin')
                            ->label(__('settings.biometric_replace_pin'))
                            ->default(false)
                            ->helperText(__('settings.biometric_replace_pin_helper')),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.login_protection'))
                    ->schema([
                        Forms\Components\TextInput::make('max_failed_login_attempts')
                            ->label(__('settings.max_failed_attempts'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(5),
                        Forms\Components\TextInput::make('lockout_duration_minutes')
                            ->label(__('settings.lockout_duration'))
                            ->numeric()
                            ->minValue(0)
                            ->default(15)
                            ->suffix(__('settings.minutes'))
                            ->helperText(__('settings.lockout_duration_helper')),
                        Forms\Components\Toggle::make('failed_attempt_alert_to_owner')
                            ->label(__('settings.alert_on_lockout'))
                            ->default(true)
                            ->helperText(__('settings.alert_on_lockout_helper')),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.device_management'))
                    ->schema([
                        Forms\Components\Select::make('device_registration_policy')
                            ->label(__('settings.device_policy'))
                            ->options([
                                'open' => __('settings.policy_open'),
                                'approval_required' => __('settings.policy_approval'),
                                'whitelist_only' => __('settings.policy_whitelist'),
                            ])
                            ->default('open')
                            ->native(false)
                            ->helperText(__('settings.device_policy_helper')),
                        Forms\Components\TextInput::make('max_devices_per_store')
                            ->label(__('settings.max_devices'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $policy = SecurityPolicyDefault::first();
        $data['updated_by'] = auth('admin')->id();

        if ($policy) {
            $policy->update($data);
        } else {
            SecurityPolicyDefault::create($data);
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_security_policies',
            entityType: 'security_policy_defaults',
            entityId: $policy?->id ?? 'new',
            details: [
                'session_timeout' => $data['session_timeout_minutes'],
                'pin_min_length' => $data['pin_min_length'],
                'device_policy' => $data['device_registration_policy'],
            ],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }
}
