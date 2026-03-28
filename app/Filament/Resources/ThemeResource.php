<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\Theme;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ui_management');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_themes');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.theme_info'))
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
                    Forms\Components\Toggle::make('is_system')
                        ->label(__('ui.is_system'))
                        ->helperText(__('ui.is_system_help'))
                        ->default(false),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.colour_palette'))
                ->schema([
                    Forms\Components\ColorPicker::make('primary_color')
                        ->label(__('ui.primary_color'))
                        ->required(),
                    Forms\Components\ColorPicker::make('secondary_color')
                        ->label(__('ui.secondary_color'))
                        ->required(),
                    Forms\Components\ColorPicker::make('background_color')
                        ->label(__('ui.background_color'))
                        ->required(),
                    Forms\Components\ColorPicker::make('text_color')
                        ->label(__('ui.text_color'))
                        ->required(),
                ])
                ->columns(4),

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
                Tables\Columns\TextColumn::make('color_swatches')
                    ->label(__('ui.colours'))
                    ->state(fn (Theme $r) => '')
                    ->formatStateUsing(fn (Theme $r) => new HtmlString(
                        '<div class="flex gap-1">' .
                        '<span class="inline-block w-5 h-5 rounded" style="background:' . e($r->primary_color) . '"></span>' .
                        '<span class="inline-block w-5 h-5 rounded" style="background:' . e($r->secondary_color) . '"></span>' .
                        '<span class="inline-block w-5 h-5 rounded" style="background:' . e($r->background_color) . '"></span>' .
                        '</div>',
                    )),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ui.slug'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('ui.is_system'))
                    ->boolean()
                    ->sortable(),
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
                Tables\Filters\TernaryFilter::make('is_system')->label(__('ui.is_system')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label(__('ui.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Theme $record) => static::getUrl('preview', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (Theme $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (Theme $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Theme $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Theme $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('ui.manage')),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('subscriptionPlans');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ThemeResource\Pages\ListThemes::route('/'),
            'create' => ThemeResource\Pages\CreateTheme::route('/create'),
            'edit' => ThemeResource\Pages\EditTheme::route('/{record}/edit'),
            'preview' => ThemeResource\Pages\PreviewTheme::route('/{record}/preview'),
        ];
    }
}
