<?php

namespace App\Filament\Resources;

use App\Domain\WameedAI\Enums\AIFeatureCategory;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AIFeatureDefinitionResource extends Resource
{
    protected static ?string $model = AIFeatureDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_features');
    }

    public static function getModelLabel(): string
    {
        return __('ai.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ai.model_label_plural');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ai.section_feature_details'))
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label(__('ai.field_slug'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->alphaDash(),
                    Forms\Components\TextInput::make('name')
                        ->label(__('ai.field_name_en'))
                        ->required()
                        ->maxLength(200),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('ai.field_name_ar'))
                        ->maxLength(200),
                    Forms\Components\Textarea::make('description')
                        ->label(__('ai.field_description_en'))
                        ->rows(3),
                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('ai.field_description_ar'))
                        ->rows(3),
                    Forms\Components\Select::make('category')
                        ->label(__('ai.field_category'))
                        ->options(collect(AIFeatureCategory::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)]))
                        ->required(),
                    Forms\Components\TextInput::make('icon')
                        ->label(__('ai.field_icon'))
                        ->maxLength(100)
                        ->placeholder('auto_awesome'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('ai.field_sort_order'))
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make(__('ai.section_ai_config'))
                ->schema([
                    Forms\Components\TextInput::make('default_model')
                        ->label(__('ai.field_default_model'))
                        ->maxLength(100)
                        ->placeholder('gpt-4o-mini'),
                    Forms\Components\TextInput::make('default_max_tokens')
                        ->label(__('ai.field_default_max_tokens'))
                        ->numeric()
                        ->default(2048),
                    Forms\Components\TextInput::make('cost_per_request_estimate')
                        ->label(__('ai.field_cost_per_request'))
                        ->numeric()
                        ->step(0.000001)
                        ->prefix('$'),
                ])->columns(3),

            Forms\Components\Section::make(__('ai.section_limits_access'))
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label(__('ai.field_is_enabled'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_premium')
                        ->label(__('ai.field_is_premium'))
                        ->default(false),
                    Forms\Components\TextInput::make('daily_limit')
                        ->label(__('ai.field_daily_limit'))
                        ->numeric()
                        ->helperText(__('ai.unlimited_helper')),
                    Forms\Components\TextInput::make('monthly_limit')
                        ->label(__('ai.field_monthly_limit'))
                        ->numeric()
                        ->helperText(__('ai.unlimited_helper')),
                ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ai.field_slug'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ai.field_name_en'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('ai.field_category'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('ai.field_is_enabled'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_premium')
                    ->label(__('ai.field_is_premium'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_model')
                    ->label(__('ai.field_model'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('daily_limit')
                    ->label(__('ai.field_daily_limit'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ai.field_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('ai.field_is_enabled')),
                Tables\Filters\TernaryFilter::make('is_premium')
                    ->label(__('ai.field_is_premium')),
                Tables\Filters\SelectFilter::make('category')
                    ->options(collect(AIFeatureCategory::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => AIFeatureDefinitionResource\Pages\ListAIFeatureDefinitions::route('/'),
            'create' => AIFeatureDefinitionResource\Pages\CreateAIFeatureDefinition::route('/create'),
            'edit' => AIFeatureDefinitionResource\Pages\EditAIFeatureDefinition::route('/{record}/edit'),
        ];
    }
}
