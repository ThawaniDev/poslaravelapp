<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\ContentOnboarding\Models\PlatformUiDefault;
use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\ContentOnboarding\Services\PosLayoutService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PlatformUiDefaultsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationGroup = 'UI Management';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_platform_defaults');
    }

    public function getTitle(): string
    {
        return __('ui.platform_ui_defaults');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    public function mount(): void
    {
        $defaults = PlatformUiDefault::pluck('value', 'key')->toArray();

        $this->form->fill($defaults);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.layout_defaults'))
                    ->description(__('ui.layout_defaults_help'))
                    ->schema([
                        Forms\Components\Select::make('handedness')
                            ->label(__('ui.handedness'))
                            ->options([
                                'left' => __('ui.handedness_left'),
                                'right' => __('ui.handedness_right'),
                                'center' => __('ui.handedness_center'),
                            ])
                            ->default('right')
                            ->native(false),
                        Forms\Components\Select::make('font_size')
                            ->label(__('ui.font_size'))
                            ->options([
                                'small' => __('ui.font_small'),
                                'medium' => __('ui.font_medium'),
                                'large' => __('ui.font_large'),
                                'extra-large' => __('ui.font_extra_large'),
                            ])
                            ->default('medium')
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make(__('ui.theme_defaults'))
                    ->description(__('ui.theme_defaults_help'))
                    ->schema([
                        Forms\Components\Select::make('theme')
                            ->label(__('ui.default_theme'))
                            ->options(fn () => Theme::where('is_active', true)->pluck('name', 'slug'))
                            ->default('light_classic')
                            ->native(false)
                            ->searchable(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($value !== null) {
                PlatformUiDefault::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );
            }
        }

        app(PosLayoutService::class)->flushPlatformCache();

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_platform_ui_defaults',
            entityType: 'platform_ui_default',
            entityId: 'global',
            details: $data,
        );

        Notification::make()
            ->title(__('ui.defaults_saved'))
            ->success()
            ->send();
    }
}
