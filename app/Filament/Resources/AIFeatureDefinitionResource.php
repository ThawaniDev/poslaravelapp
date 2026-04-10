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
        return 'AI Feature';
    }

    public static function getPluralModelLabel(): string
    {
        return 'AI Features';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Feature Details')
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->alphaDash(),
                    Forms\Components\TextInput::make('name')
                        ->label('Name (English)')
                        ->required()
                        ->maxLength(200),
                    Forms\Components\TextInput::make('name_ar')
                        ->label('Name (Arabic)')
                        ->maxLength(200),
                    Forms\Components\Textarea::make('description')
                        ->label('Description (English)')
                        ->rows(3),
                    Forms\Components\Textarea::make('description_ar')
                        ->label('Description (Arabic)')
                        ->rows(3),
                    Forms\Components\Select::make('category')
                        ->label('Category')
                        ->options(collect(AIFeatureCategory::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)]))
                        ->required(),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100)
                        ->placeholder('auto_awesome'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make('AI Configuration')
                ->schema([
                    Forms\Components\TextInput::make('default_model')
                        ->label('Default Model')
                        ->maxLength(100)
                        ->placeholder('gpt-4o-mini'),
                    Forms\Components\TextInput::make('default_max_tokens')
                        ->label('Default Max Tokens')
                        ->numeric()
                        ->default(2048),
                    Forms\Components\TextInput::make('cost_per_request_estimate')
                        ->label('Cost Per Request (USD)')
                        ->numeric()
                        ->step(0.000001)
                        ->prefix('$'),
                ])->columns(3),

            Forms\Components\Section::make('Limits & Access')
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(true),
                    Forms\Components\Toggle::make('is_premium')
                        ->label('Premium Only')
                        ->default(false),
                    Forms\Components\TextInput::make('daily_limit')
                        ->label('Daily Limit')
                        ->numeric()
                        ->helperText('0 = unlimited'),
                    Forms\Components\TextInput::make('monthly_limit')
                        ->label('Monthly Limit')
                        ->numeric()
                        ->helperText('0 = unlimited'),
                ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_premium')
                    ->label('Premium')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_model')
                    ->label('Model')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('daily_limit')
                    ->label('Daily Limit')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
                Tables\Filters\TernaryFilter::make('is_premium')
                    ->label('Premium'),
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
