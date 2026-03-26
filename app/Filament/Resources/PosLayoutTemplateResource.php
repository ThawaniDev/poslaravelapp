<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PosLayoutTemplateResource extends Resource
{
    protected static ?string $model = PosLayoutTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_layouts');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'layout_key'];
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.basic_info'))
                ->schema([
                    Forms\Components\Select::make('business_type_id')
                        ->label(__('ui.business_type'))
                        ->options(BusinessType::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('name')
                        ->label(__('ui.name_en'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state, Forms\Get $get) {
                            $bt = BusinessType::find($get('business_type_id'));
                            $prefix = $bt ? Str::slug($bt->slug) . '_' : '';
                            $set('layout_key', $prefix . Str::slug($state, '_'));
                        }),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('ui.name_ar'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('layout_key')
                        ->label(__('ui.layout_key'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('ui.layout_key_help')),
                    Forms\Components\TextInput::make('description')
                        ->label(__('ui.description'))
                        ->maxLength(500),
                    Forms\Components\TextInput::make('preview_image_url')
                        ->label(__('ui.preview_image_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('ui.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_default')
                        ->label(__('ui.is_default'))
                        ->helperText(__('ui.is_default_layout_help')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.layout_config'))
                ->description(__('ui.layout_config_desc'))
                ->schema([
                    Forms\Components\Select::make('config.layout_type')
                        ->label(__('ui.layout_type'))
                        ->options([
                            'grid' => __('ui.layout_type_grid'),
                            'split' => __('ui.layout_type_split'),
                            'minimal' => __('ui.layout_type_minimal'),
                            'table_view' => __('ui.layout_type_table_view'),
                        ])
                        ->required(),
                    Forms\Components\Select::make('config.cart_position')
                        ->label(__('ui.cart_position'))
                        ->options([
                            'right' => __('ui.cart_position_right'),
                            'bottom' => __('ui.cart_position_bottom'),
                            'floating' => __('ui.cart_position_floating'),
                        ])
                        ->default('right'),
                    Forms\Components\TextInput::make('config.cart_width')
                        ->label(__('ui.cart_width'))
                        ->numeric()
                        ->minValue(20)
                        ->maxValue(60)
                        ->default(35)
                        ->suffix('%'),
                    Forms\Components\Toggle::make('config.show_categories')
                        ->label(__('ui.show_categories'))
                        ->default(true),
                    Forms\Components\Select::make('config.category_style')
                        ->label(__('ui.category_style'))
                        ->options([
                            'tabs' => __('ui.category_style_tabs'),
                            'sidebar' => __('ui.category_style_sidebar'),
                            'icons' => __('ui.category_style_icons'),
                        ])
                        ->default('tabs'),
                    Forms\Components\Select::make('config.product_display')
                        ->label(__('ui.product_display'))
                        ->options([
                            'grid' => __('ui.product_display_grid'),
                            'list' => __('ui.product_display_list'),
                            'images' => __('ui.product_display_images'),
                        ])
                        ->default('grid'),
                    Forms\Components\TextInput::make('config.product_columns')
                        ->label(__('ui.product_columns'))
                        ->numeric()
                        ->minValue(2)
                        ->maxValue(8)
                        ->default(4),
                    Forms\Components\Toggle::make('config.show_images')
                        ->label(__('ui.show_images'))
                        ->default(true),
                    Forms\Components\TagsInput::make('config.quick_actions')
                        ->label(__('ui.quick_actions'))
                        ->helperText(__('ui.quick_actions_help')),
                    Forms\Components\TagsInput::make('config.payment_buttons')
                        ->label(__('ui.payment_buttons'))
                        ->helperText(__('ui.payment_buttons_help')),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.special_features'))
                ->schema([
                    Forms\Components\Toggle::make('config.special_features.weighable_items')
                        ->label(__('ui.weighable_items')),
                    Forms\Components\Toggle::make('config.special_features.table_management')
                        ->label(__('ui.table_management')),
                    Forms\Components\Toggle::make('config.special_features.kitchen_display')
                        ->label(__('ui.kitchen_display')),
                    Forms\Components\Toggle::make('config.special_features.prescription_mode')
                        ->label(__('ui.prescription_mode')),
                    Forms\Components\Toggle::make('config.special_features.imei_tracking')
                        ->label(__('ui.imei_tracking')),
                ])
                ->columns(3),

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
                Tables\Columns\ImageColumn::make('preview_image_url')
                    ->label(__('ui.preview'))
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=POS&background=FD8209&color=fff')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name_en'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (PosLayoutTemplate $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('businessType.name')
                    ->label(__('ui.business_type'))
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('layout_key')
                    ->label(__('ui.layout_key'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('ui.is_default'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ui.sort_order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriptionPlans.name')
                    ->label(__('ui.visible_plans'))
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_type_id')
                    ->label(__('ui.business_type'))
                    ->options(BusinessType::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
                Tables\Filters\TernaryFilter::make('is_default')->label(__('ui.is_default')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('set_default')
                    ->label(__('ui.set_as_default'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (PosLayoutTemplate $r) => ! $r->is_default)
                    ->action(function (PosLayoutTemplate $record) {
                        PosLayoutTemplate::where('business_type_id', $record->business_type_id)
                            ->update(['is_default' => false]);
                        $record->update(['is_default' => true]);
                    }),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (PosLayoutTemplate $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (PosLayoutTemplate $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (PosLayoutTemplate $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (PosLayoutTemplate $record) => $record->update(['is_active' => ! $record->is_active])),
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
            'index' => PosLayoutTemplateResource\Pages\ListPosLayoutTemplates::route('/'),
            'create' => PosLayoutTemplateResource\Pages\CreatePosLayoutTemplate::route('/create'),
            'edit' => PosLayoutTemplateResource\Pages\EditPosLayoutTemplate::route('/{record}/edit'),
        ];
    }
}
