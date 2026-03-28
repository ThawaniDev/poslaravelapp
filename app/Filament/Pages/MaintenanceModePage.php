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

class MaintenanceModePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
    protected static ?int $navigationSort = 16;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.maintenance_mode');
    }

    public function getTitle(): string
    {
        return __('settings.maintenance_mode');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.edit']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::where('group', 'maintenance')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.maintenance_status'))
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_enabled')
                            ->label(__('settings.maintenance_enabled'))
                            ->live()
                            ->helperText(__('settings.maintenance_enabled_helper')),
                    ]),

                Forms\Components\Section::make(__('settings.maintenance_message'))
                    ->schema([
                        Forms\Components\Textarea::make('maintenance_banner_en')
                            ->label(__('settings.banner_message_en'))
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\Textarea::make('maintenance_banner_ar')
                            ->label(__('settings.banner_message_ar'))
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\DateTimePicker::make('maintenance_expected_end')
                            ->label(__('settings.expected_end_time'))
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.allowed_ips'))
                    ->schema([
                        Forms\Components\Textarea::make('maintenance_allowed_ips')
                            ->label(__('settings.allowed_ips'))
                            ->rows(5)
                            ->helperText(__('settings.allowed_ips_helper'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'maintenance', 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: ($data['maintenance_enabled'] ?? false)
                ? 'enable_maintenance_mode'
                : 'disable_maintenance_mode',
            entityType: 'system_setting',
            entityId: 'maintenance',
            details: ['is_enabled' => $data['maintenance_enabled'] ?? false],
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }
}
