<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Enums\SignageTemplateType;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\ContentOnboarding\Models\SignageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SignageTemplateResource extends Resource
{
    protected static ?string $model = SignageTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ui_management');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_signage_templates');
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
                    Forms\Components\Select::make('template_type')
                        ->label(__('ui.template_type'))
                        ->options(collect(SignageTemplateType::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.signage_type_' . $c->value)]))
                        ->required(),
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
                        ->helperText(__('ui.signage_business_types_help')),
                ]),

            Forms\Components\Section::make(__('ui.layout_config'))
                ->description(__('ui.signage_layout_config_desc'))
                ->schema([
                    Forms\Components\Repeater::make('layout_config')
                        ->label(__('ui.regions'))
                        ->schema([
                            Forms\Components\TextInput::make('region_id')
                                ->label(__('ui.region_id'))
                                ->required()
                                ->maxLength(50),
                            Forms\Components\Select::make('type')
                                ->label(__('ui.region_type'))
                                ->options([
                                    'image' => __('ui.region_image'),
                                    'text' => __('ui.region_text'),
                                    'product_grid' => __('ui.region_product_grid'),
                                    'video' => __('ui.region_video'),
                                    'clock' => __('ui.region_clock'),
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('position.x')
                                ->label(__('ui.position_x'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0),
                            Forms\Components\TextInput::make('position.y')
                                ->label(__('ui.position_y'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0),
                            Forms\Components\TextInput::make('position.w')
                                ->label(__('ui.dimension_w'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(100)
                                ->default(100),
                            Forms\Components\TextInput::make('position.h')
                                ->label(__('ui.dimension_h'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(100)
                                ->default(100),
                            Forms\Components\Textarea::make('default_content')
                                ->label(__('ui.default_content'))
                                ->maxLength(1000),
                        ])
                        ->columns(4)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['region_id'] ?? null),
                ]),

            Forms\Components\Section::make(__('ui.styling'))
                ->schema([
                    Forms\Components\ColorPicker::make('background_color')
                        ->label(__('ui.background_color'))
                        ->default('#FFFFFF'),
                    Forms\Components\ColorPicker::make('text_color')
                        ->label(__('ui.text_color'))
                        ->default('#333333'),
                    Forms\Components\TextInput::make('font_family')
                        ->label(__('ui.font_family'))
                        ->default('system')
                        ->maxLength(50),
                    Forms\Components\Select::make('transition_style')
                        ->label(__('ui.transition_style'))
                        ->options([
                            'fade' => __('ui.animation_fade'),
                            'slide' => __('ui.animation_slide'),
                            'none' => __('ui.animation_none'),
                        ])
                        ->default('fade'),
                ])
                ->columns(4),

            Forms\Components\Section::make(__('ui.placeholder_content'))
                ->description(__('ui.placeholder_content_desc'))
                ->schema([
                    Forms\Components\KeyValue::make('placeholder_content')
                        ->label(__('ui.placeholder_content'))
                        ->keyLabel(__('ui.region_id'))
                        ->valueLabel(__('ui.default_content')),
                ]),

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
                    ->description(fn (SignageTemplate $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ui.slug'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('template_type')
                    ->label(__('ui.template_type'))
                    ->badge()
                    ->color(fn (SignageTemplate $r) => match ($r->template_type) {
                        SignageTemplateType::MenuBoard => 'success',
                        SignageTemplateType::PromoSlideshow => 'warning',
                        SignageTemplateType::QueueDisplay => 'info',
                        SignageTemplateType::InfoBoard => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (SignageTemplate $r) => __('ui.signage_type_' . $r->template_type->value)),
                Tables\Columns\TextColumn::make('businessTypes.name')
                    ->label(__('ui.business_types'))
                    ->badge()
                    ->color('primary')
                    ->separator(', '),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('template_type')
                    ->label(__('ui.template_type'))
                    ->options(collect(SignageTemplateType::cases())->mapWithKeys(fn ($c) => [$c->value => __('ui.signage_type_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label(__('ui.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (SignageTemplate $record) => static::getUrl('preview', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (SignageTemplate $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (SignageTemplate $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (SignageTemplate $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (SignageTemplate $record) => $record->update(['is_active' => ! $record->is_active])),
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
            'index' => SignageTemplateResource\Pages\ListSignageTemplates::route('/'),
            'create' => SignageTemplateResource\Pages\CreateSignageTemplate::route('/create'),
            'edit' => SignageTemplateResource\Pages\EditSignageTemplate::route('/{record}/edit'),
            'preview' => SignageTemplateResource\Pages\PreviewSignageTemplate::route('/{record}/preview'),
        ];
    }
}
