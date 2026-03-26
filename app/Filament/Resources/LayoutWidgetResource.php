<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Enums\WidgetCategory;
use App\Domain\ContentOnboarding\Models\LayoutWidget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LayoutWidgetResource extends Resource
{
    protected static ?string $model = LayoutWidget::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_layout_widgets');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.widget_info'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('ui.name_en'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state, '_'))),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('ui.name_ar'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('ui.slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('category')
                        ->label(__('ui.widget_category'))
                        ->options(collect(WidgetCategory::cases())->mapWithKeys(
                            fn ($c) => [$c->value => __('ui.widget_category_' . $c->value)],
                        ))
                        ->required(),
                    Forms\Components\TextInput::make('icon')
                        ->label(__('ui.icon'))
                        ->maxLength(50)
                        ->helperText(__('ui.icon_help')),
                    Forms\Components\Textarea::make('description')
                        ->label(__('ui.description_en'))
                        ->maxLength(500),
                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('ui.description_ar'))
                        ->maxLength(500),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('ui.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_required')
                        ->label(__('ui.is_required'))
                        ->helperText(__('ui.widget_is_required_help')),
                    Forms\Components\Toggle::make('is_resizable')
                        ->label(__('ui.is_resizable'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.widget_sizing'))
                ->schema([
                    Forms\Components\TextInput::make('default_width')
                        ->label(__('ui.default_width'))
                        ->numeric()
                        ->required()
                        ->default(6)
                        ->minValue(1)
                        ->maxValue(24),
                    Forms\Components\TextInput::make('default_height')
                        ->label(__('ui.default_height'))
                        ->numeric()
                        ->required()
                        ->default(4)
                        ->minValue(1)
                        ->maxValue(16),
                    Forms\Components\TextInput::make('min_width')
                        ->label(__('ui.min_width'))
                        ->numeric()
                        ->required()
                        ->default(2)
                        ->minValue(1),
                    Forms\Components\TextInput::make('min_height')
                        ->label(__('ui.min_height'))
                        ->numeric()
                        ->required()
                        ->default(2)
                        ->minValue(1),
                    Forms\Components\TextInput::make('max_width')
                        ->label(__('ui.max_width'))
                        ->numeric()
                        ->required()
                        ->default(24)
                        ->minValue(1),
                    Forms\Components\TextInput::make('max_height')
                        ->label(__('ui.max_height'))
                        ->numeric()
                        ->required()
                        ->default(16)
                        ->minValue(1),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('ui.widget_properties'))
                ->schema([
                    Forms\Components\KeyValue::make('properties_schema')
                        ->label(__('ui.properties_schema'))
                        ->helperText(__('ui.properties_schema_help'))
                        ->default([]),
                    Forms\Components\KeyValue::make('default_properties')
                        ->label(__('ui.default_properties'))
                        ->helperText(__('ui.default_properties_help'))
                        ->default([]),
                ]),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name_en'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (LayoutWidget $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ui.slug'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('ui.widget_category'))
                    ->badge()
                    ->color(fn (LayoutWidget $r) => match ($r->category) {
                        WidgetCategory::Core => 'danger',
                        WidgetCategory::Commerce => 'success',
                        WidgetCategory::Display => 'info',
                        WidgetCategory::Utility => 'warning',
                        WidgetCategory::Custom => 'gray',
                    })
                    ->formatStateUsing(fn (LayoutWidget $r) => __('ui.widget_category_' . $r->category->value)),
                Tables\Columns\TextColumn::make('default_width')
                    ->label(__('ui.size'))
                    ->formatStateUsing(fn (LayoutWidget $r) => "{$r->default_width}×{$r->default_height}"),
                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('ui.is_required'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ui.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('ui.widget_category'))
                    ->options(collect(WidgetCategory::cases())->mapWithKeys(
                        fn ($c) => [$c->value => __('ui.widget_category_' . $c->value)],
                    )),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
                Tables\Filters\TernaryFilter::make('is_required')->label(__('ui.is_required')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (LayoutWidget $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (LayoutWidget $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (LayoutWidget $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (LayoutWidget $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => LayoutWidgetResource\Pages\ListLayoutWidgets::route('/'),
            'create' => LayoutWidgetResource\Pages\CreateLayoutWidget::route('/create'),
            'edit' => LayoutWidgetResource\Pages\EditLayoutWidget::route('/{record}/edit'),
        ];
    }
}
