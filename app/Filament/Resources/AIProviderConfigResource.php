<?php

namespace App\Filament\Resources;

use App\Domain\WameedAI\Enums\AIProvider;
use App\Domain\WameedAI\Models\AILlmModel;
use App\Domain\WameedAI\Models\AIProviderConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AIProviderConfigResource extends Resource
{
    protected static ?string $model = AILlmModel::class;

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
        return 'AI Model';
    }

    public static function getPluralModelLabel(): string
    {
        return 'AI Models & Providers';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Model Configuration')
                ->schema([
                    Forms\Components\Select::make('provider')
                        ->label('Provider')
                        ->options(collect(AIProvider::cases())->mapWithKeys(fn ($p) => [$p->value => strtoupper($p->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('model_id')
                        ->label('Model ID')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('gpt-4o-mini'),
                    Forms\Components\TextInput::make('display_name')
                        ->label('Display Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('GPT-4o Mini'),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    Forms\Components\TextInput::make('api_key_encrypted')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->maxLength(500)
                        ->helperText('Stored encrypted. Leave blank to keep existing key.')
                        ->dehydrateStateUsing(fn ($state) => $state ?: null)
                        ->dehydrated(fn ($state) => filled($state)),
                ])->columns(2),

            Forms\Components\Section::make('Capabilities')
                ->schema([
                    Forms\Components\TextInput::make('max_context_tokens')
                        ->label('Max Context Tokens')
                        ->numeric()
                        ->default(128000),
                    Forms\Components\TextInput::make('max_output_tokens')
                        ->label('Max Output Tokens')
                        ->numeric()
                        ->default(4096),
                    Forms\Components\Toggle::make('supports_vision')
                        ->label('Supports Vision')
                        ->default(false),
                    Forms\Components\Toggle::make('supports_json_mode')
                        ->label('Supports JSON Mode')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Pricing (per 1M tokens)')
                ->schema([
                    Forms\Components\TextInput::make('input_price_per_1m')
                        ->label('Input Price ($)')
                        ->numeric()
                        ->step(0.01)
                        ->default(0.15)
                        ->prefix('$'),
                    Forms\Components\TextInput::make('output_price_per_1m')
                        ->label('Output Price ($)')
                        ->numeric()
                        ->step(0.01)
                        ->default(0.60)
                        ->prefix('$'),
                ])->columns(2),

            Forms\Components\Section::make('Status')
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(true),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default Model')
                        ->default(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0),
                ])->columns(3),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Model Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('provider')
                            ->label('Provider')
                            ->badge(),
                        Infolists\Components\TextEntry::make('model_id')
                            ->label('Model ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('display_name')
                            ->label('Display Name'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Capabilities')
                    ->schema([
                        Infolists\Components\TextEntry::make('max_context_tokens')
                            ->label('Max Context')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('max_output_tokens')
                            ->label('Max Output')
                            ->numeric(),
                        Infolists\Components\IconEntry::make('supports_vision')
                            ->label('Vision')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('supports_json_mode')
                            ->label('JSON Mode')
                            ->boolean(),
                    ])->columns(4),

                Infolists\Components\Section::make('Pricing')
                    ->schema([
                        Infolists\Components\TextEntry::make('input_price_per_1m')
                            ->label('Input ($/1M tokens)')
                            ->money('usd'),
                        Infolists\Components\TextEntry::make('output_price_per_1m')
                            ->label('Output ($/1M tokens)')
                            ->money('usd'),
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
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Model')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AILlmModel $record) => $record->model_id),
                Tables\Columns\TextColumn::make('input_price_per_1m')
                    ->label('Input $/1M')
                    ->money('usd')
                    ->sortable(),
                Tables\Columns\TextColumn::make('output_price_per_1m')
                    ->label('Output $/1M')
                    ->money('usd')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_context_tokens')
                    ->label('Context')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('supports_vision')
                    ->label('Vision')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('supports_json_mode')
                    ->label('JSON')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
                Tables\Filters\SelectFilter::make('provider')
                    ->options(collect(AIProvider::cases())->mapWithKeys(fn ($p) => [$p->value => strtoupper($p->value)])),
                Tables\Filters\TernaryFilter::make('supports_vision')
                    ->label('Vision'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
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
