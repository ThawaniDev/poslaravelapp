<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Enums\AnimationStyle;
use App\Domain\ContentOnboarding\Enums\CfdCartLayout;
use App\Domain\ContentOnboarding\Enums\CfdIdleLayout;
use App\Domain\ContentOnboarding\Enums\ThankYouAnimation;
use App\Domain\ContentOnboarding\Models\CfdTheme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CfdThemeResource extends Resource
{
    protected static ?string $model = CfdTheme::class;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_cfd_themes');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.basic_info'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('ui.name'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('ui.slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.colour_palette'))
                ->schema([
                    Forms\Components\ColorPicker::make('background_color')
                        ->label(__('ui.background_color'))
                        ->required(),
                    Forms\Components\ColorPicker::make('text_color')
                        ->label(__('ui.text_color'))
                        ->required(),
                    Forms\Components\ColorPicker::make('accent_color')
                        ->label(__('ui.accent_color'))
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('ui.display_settings'))
                ->schema([
                    Forms\Components\TextInput::make('font_family')
                        ->label(__('ui.font_family'))
                        ->default('system')
                        ->maxLength(50),
                    Forms\Components\Select::make('cart_layout')
                        ->label(__('ui.cart_layout'))
                        ->options(collect(CfdCartLayout::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.cfd_cart_' . $c->value)]))
                        ->default(CfdCartLayout::List->value),
                    Forms\Components\Select::make('idle_layout')
                        ->label(__('ui.idle_layout'))
                        ->options(collect(CfdIdleLayout::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.cfd_idle_' . $c->value)]))
                        ->default(CfdIdleLayout::Slideshow->value),
                    Forms\Components\Select::make('animation_style')
                        ->label(__('ui.animation_style'))
                        ->options(collect(AnimationStyle::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.animation_' . $c->value)]))
                        ->default(AnimationStyle::Fade->value),
                    Forms\Components\TextInput::make('transition_seconds')
                        ->label(__('ui.transition_seconds'))
                        ->numeric()
                        ->default(5)
                        ->minValue(1)
                        ->maxValue(30)
                        ->suffix(__('ui.seconds')),
                    Forms\Components\Select::make('thank_you_animation')
                        ->label(__('ui.thank_you_animation'))
                        ->options(collect(ThankYouAnimation::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.thankyou_' . $c->value)]))
                        ->default(ThankYouAnimation::Check->value),
                    Forms\Components\Toggle::make('show_store_logo')
                        ->label(__('ui.show_store_logo'))
                        ->default(true),
                    Forms\Components\Toggle::make('show_running_total')
                        ->label(__('ui.show_running_total'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.package_visibility'))
                ->schema([
                    Forms\Components\Select::make('subscriptionPlans')
                        ->label(__('ui.visible_plans'))
                        ->relationship('subscriptionPlans', 'name')
                        ->multiple()
                        ->preload()
                        ->helperText(__('ui.visible_plans_help')),
                ]),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ui.slug'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('idle_layout')
                    ->label(__('ui.idle_layout'))
                    ->badge()
                    ->color(fn (CfdTheme $r) => match ($r->idle_layout) {
                        CfdIdleLayout::Slideshow => 'info',
                        CfdIdleLayout::StaticImage => 'success',
                        CfdIdleLayout::VideoLoop => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (CfdTheme $r) => __('ui.cfd_idle_' . $r->idle_layout->value)),
                Tables\Columns\TextColumn::make('animation_style')
                    ->label(__('ui.animation_style'))
                    ->badge()
                    ->formatStateUsing(fn (CfdTheme $r) => __('ui.animation_' . $r->animation_style->value)),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriptionPlans.name')
                    ->label(__('ui.visible_plans'))
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
                Tables\Filters\SelectFilter::make('idle_layout')
                    ->label(__('ui.idle_layout'))
                    ->options(collect(CfdIdleLayout::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.cfd_idle_' . $c->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (CfdTheme $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (CfdTheme $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (CfdTheme $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (CfdTheme $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => CfdThemeResource\Pages\ListCfdThemes::route('/'),
            'create' => CfdThemeResource\Pages\CreateCfdTheme::route('/create'),
            'edit' => CfdThemeResource\Pages\EditCfdTheme::route('/{record}/edit'),
        ];
    }
}
