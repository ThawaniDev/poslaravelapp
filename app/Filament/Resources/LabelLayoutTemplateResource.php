<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Enums\BarcodeType;
use App\Domain\ContentOnboarding\Enums\BorderStyle;
use App\Domain\ContentOnboarding\Enums\LabelType;
use App\Domain\ContentOnboarding\Models\LabelLayoutTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LabelLayoutTemplateResource extends Resource
{
    protected static ?string $model = LabelLayoutTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ui_management');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_label_templates');
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
                        ->label(__('ui.name_en'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('ui.name_ar'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('ui.slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('label_type')
                        ->label(__('ui.label_type'))
                        ->options(collect(LabelType::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.label_type_' . $c->value)]))
                        ->required(),
                    Forms\Components\TextInput::make('label_width_mm')
                        ->label(__('ui.label_width'))
                        ->numeric()
                        ->required()
                        ->minValue(10)
                        ->maxValue(200)
                        ->suffix('mm'),
                    Forms\Components\TextInput::make('label_height_mm')
                        ->label(__('ui.label_height'))
                        ->numeric()
                        ->required()
                        ->minValue(10)
                        ->maxValue(200)
                        ->suffix('mm'),
                    Forms\Components\TextInput::make('preview_image_url')
                        ->label(__('ui.preview_image_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.business_types'))
                ->schema([
                    Forms\Components\Select::make('businessTypes')
                        ->label(__('ui.business_types'))
                        ->relationship('businessTypes', 'name')
                        ->multiple()
                        ->preload()
                        ->helperText(__('ui.label_business_types_help')),
                ]),

            Forms\Components\Section::make(__('ui.barcode_settings'))
                ->schema([
                    Forms\Components\Select::make('barcode_type')
                        ->label(__('ui.barcode_type'))
                        ->options(collect(BarcodeType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->value]))
                        ->default(BarcodeType::CODE128->value),
                    Forms\Components\TextInput::make('barcode_position.x')
                        ->label(__('ui.barcode_x'))
                        ->numeric()
                        ->default(10)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('barcode_position.y')
                        ->label(__('ui.barcode_y'))
                        ->numeric()
                        ->default(10)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('barcode_position.w')
                        ->label(__('ui.barcode_w'))
                        ->numeric()
                        ->default(80)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('barcode_position.h')
                        ->label(__('ui.barcode_h'))
                        ->numeric()
                        ->default(30)
                        ->suffix('%'),
                    Forms\Components\Toggle::make('show_barcode_number')
                        ->label(__('ui.show_barcode_number'))
                        ->default(true),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('ui.field_layout'))
                ->description(__('ui.field_layout_desc'))
                ->schema([
                    Forms\Components\Repeater::make('field_layout')
                        ->label(__('ui.fields'))
                        ->schema([
                            Forms\Components\Select::make('field_key')
                                ->label(__('ui.field_key'))
                                ->options([
                                    'product_name' => __('ui.field_product_name'),
                                    'product_name_ar' => __('ui.field_product_name_ar'),
                                    'sku' => __('ui.field_sku'),
                                    'barcode' => __('ui.field_barcode'),
                                    'price' => __('ui.field_price'),
                                    'price_before_discount' => __('ui.field_price_before_discount'),
                                    'weight' => __('ui.field_weight'),
                                    'unit' => __('ui.field_unit'),
                                    'expiry_date' => __('ui.field_expiry_date'),
                                    'batch_number' => __('ui.field_batch_number'),
                                    'manufacture_date' => __('ui.field_manufacture_date'),
                                    'origin_country' => __('ui.field_origin_country'),
                                    'karat' => __('ui.field_karat'),
                                    'making_charge' => __('ui.field_making_charge'),
                                    'drug_schedule' => __('ui.field_drug_schedule'),
                                    'store_name' => __('ui.field_store_name'),
                                    'custom_text' => __('ui.field_custom_text'),
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('label_en')
                                ->label(__('ui.field_label_en'))
                                ->maxLength(50),
                            Forms\Components\TextInput::make('label_ar')
                                ->label(__('ui.field_label_ar'))
                                ->maxLength(50),
                            Forms\Components\TextInput::make('position.x')
                                ->label(__('ui.position_x'))
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('position.y')
                                ->label(__('ui.position_y'))
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('position.w')
                                ->label(__('ui.dimension_w'))
                                ->numeric()
                                ->default(50),
                            Forms\Components\TextInput::make('position.h')
                                ->label(__('ui.dimension_h'))
                                ->numeric()
                                ->default(10),
                            Forms\Components\TextInput::make('font_size')
                                ->label(__('ui.field_font_size'))
                                ->numeric()
                                ->default(12),
                            Forms\Components\Toggle::make('is_bold')
                                ->label(__('ui.field_is_bold'))
                                ->default(false),
                            Forms\Components\Select::make('alignment')
                                ->label(__('ui.field_alignment'))
                                ->options(['left' => __('ui.align_left'), 'center' => __('ui.align_center'), 'right' => __('ui.align_right')])
                                ->default('left'),
                        ])
                        ->columns(5)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['field_key'] ?? null),
                ]),

            Forms\Components\Section::make(__('ui.styling'))
                ->schema([
                    Forms\Components\TextInput::make('font_family')
                        ->label(__('ui.font_family'))
                        ->default('system')
                        ->maxLength(50),
                    Forms\Components\Select::make('default_font_size')
                        ->label(__('ui.default_font_size'))
                        ->options(['small' => __('ui.font_small'), 'medium' => __('ui.font_medium'), 'large' => __('ui.font_large'), 'extra-large' => __('ui.font_extra_large')])
                        ->default('medium'),
                    Forms\Components\Toggle::make('show_border')
                        ->label(__('ui.show_border'))
                        ->default(true),
                    Forms\Components\Select::make('border_style')
                        ->label(__('ui.border_style'))
                        ->options(collect(BorderStyle::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.border_' . $c->value)]))
                        ->default(BorderStyle::Solid->value),
                    Forms\Components\ColorPicker::make('background_color')
                        ->label(__('ui.background_color'))
                        ->default('#FFFFFF'),
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
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name_en'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (LabelLayoutTemplate $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('label_type')
                    ->label(__('ui.label_type'))
                    ->badge()
                    ->color(fn (LabelLayoutTemplate $r) => match ($r->label_type) {
                        LabelType::Barcode => 'gray',
                        LabelType::Price => 'success',
                        LabelType::Shelf => 'info',
                        LabelType::Jewelry => 'warning',
                        LabelType::Pharmacy => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (LabelLayoutTemplate $r) => __('ui.label_type_' . $r->label_type->value)),
                Tables\Columns\TextColumn::make('label_size')
                    ->label(__('ui.label_size'))
                    ->state(fn (LabelLayoutTemplate $r) => $r->label_width_mm . '×' . $r->label_height_mm . 'mm')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('businessTypes.name')
                    ->label(__('ui.business_types'))
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('label_type')
                    ->label(__('ui.label_type'))
                    ->options(collect(LabelType::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.label_type_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label(__('ui.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (LabelLayoutTemplate $record) => static::getUrl('preview', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (LabelLayoutTemplate $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (LabelLayoutTemplate $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (LabelLayoutTemplate $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (LabelLayoutTemplate $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('businessTypes');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => LabelLayoutTemplateResource\Pages\ListLabelLayoutTemplates::route('/'),
            'create' => LabelLayoutTemplateResource\Pages\CreateLabelLayoutTemplate::route('/create'),
            'edit' => LabelLayoutTemplateResource\Pages\EditLabelLayoutTemplate::route('/{record}/edit'),
            'preview' => LabelLayoutTemplateResource\Pages\PreviewLabelLayoutTemplate::route('/{record}/preview'),
        ];
    }
}
