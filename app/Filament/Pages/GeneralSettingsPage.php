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

class GeneralSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.general_settings');
    }

    public function getTitle(): string
    {
        return __('settings.general_settings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.edit']);
    }

    public function mount(): void
    {
        $settings = SystemSetting::whereIn('group', ['general', 'vat', 'locale', 'sync'])
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('settings.locale_currency'))
                    ->schema([
                        Forms\Components\Select::make('locale_default_language')
                            ->label(__('settings.default_language'))
                            ->options(['ar' => 'العربية', 'en' => 'English'])
                            ->default('ar')
                            ->native(false),
                        Forms\Components\TextInput::make('locale_default_currency')
                            ->label(__('settings.default_currency'))
                            ->default('SAR')
                            ->maxLength(5),
                        Forms\Components\Select::make('locale_currency_symbol_position')
                            ->label(__('settings.currency_symbol_position'))
                            ->options(['before' => __('settings.before'), 'after' => __('settings.after')])
                            ->default('after')
                            ->native(false),
                        Forms\Components\Select::make('locale_number_format')
                            ->label(__('settings.number_format'))
                            ->options(['western' => __('settings.western_digits'), 'arabic' => __('settings.arabic_digits')])
                            ->default('western')
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.vat_settings'))
                    ->schema([
                        Forms\Components\TextInput::make('vat_rate')
                            ->label(__('settings.vat_rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(15)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('vat_registration_number')
                            ->label(__('settings.vat_registration_number'))
                            ->maxLength(50),
                    ])->columns(2),

                Forms\Components\Section::make(__('settings.sync_config'))
                    ->schema([
                        Forms\Components\Select::make('sync_conflict_policy')
                            ->label(__('settings.conflict_policy'))
                            ->options([
                                'server_wins' => __('settings.server_wins'),
                                'client_wins' => __('settings.client_wins'),
                                'last_write_wins' => __('settings.last_write_wins'),
                                'manual' => __('settings.manual_resolution'),
                            ])
                            ->default('server_wins')
                            ->native(false),
                        Forms\Components\TextInput::make('sync_interval_seconds')
                            ->label(__('settings.sync_interval_seconds'))
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(3600)
                            ->default(300)
                            ->suffix(__('settings.seconds')),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $groupMap = ['locale' => 'locale', 'vat' => 'vat', 'sync' => 'sync'];

        foreach ($data as $key => $value) {
            $prefix = explode('_', $key)[0];
            $group = $groupMap[$prefix] ?? 'general';

            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'updated_by' => auth('admin')->id()],
            );
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_general_settings',
            entityType: 'system_setting',
            entityId: 'general',
            details: array_keys($data),
        );

        Notification::make()->title(__('settings.config_saved'))->success()->send();
    }
}
