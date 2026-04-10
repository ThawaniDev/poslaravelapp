<?php

namespace App\Filament\Resources;

use App\Domain\WameedAI\Enums\AIProvider;
use App\Domain\WameedAI\Models\AIProviderConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AIProviderConfigResource extends Resource
{
    protected static ?string $model = AIProviderConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_providers');
    }

    public static function getModelLabel(): string
    {
        return 'AI Provider';
    }

    public static function getPluralModelLabel(): string
    {
        return 'AI Providers';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Provider Configuration')
                ->schema([
                    Forms\Components\Select::make('provider')
                        ->label('Provider')
                        ->options(collect(AIProvider::cases())->mapWithKeys(fn ($p) => [$p->value => strtoupper($p->value)]))
                        ->required(),
                    Forms\Components\TextInput::make('default_model')
                        ->label('Default Model')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('gpt-4o'),
                    Forms\Components\TextInput::make('api_key_encrypted')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->maxLength(500)
                        ->helperText('Stored encrypted. Leave blank to keep existing key.'),
                    Forms\Components\TextInput::make('max_tokens_per_request')
                        ->label('Max Tokens Per Request')
                        ->numeric()
                        ->default(4096)
                        ->minValue(100)
                        ->maxValue(128000),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_model')
                    ->label('Default Model')
                    ->searchable(),
                Tables\Columns\TextColumn::make('max_tokens_per_request')
                    ->label('Max Tokens')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('provider')
                    ->options(collect(AIProvider::cases())->mapWithKeys(fn ($p) => [$p->value => strtoupper($p->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('provider');
    }

    public static function getPages(): array
    {
        return [
            'index' => AIProviderConfigResource\Pages\ListAIProviderConfigs::route('/'),
            'create' => AIProviderConfigResource\Pages\CreateAIProviderConfig::route('/create'),
            'edit' => AIProviderConfigResource\Pages\EditAIProviderConfig::route('/{record}/edit'),
        ];
    }
}
