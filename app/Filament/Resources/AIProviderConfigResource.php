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
        return __('ai.model_provider_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ai.model_provider_label_plural');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ai.section_model_config'))
                ->schema([
                    Forms\Components\Select::make('provider')
                        ->label(__('ai.field_provider'))
                        ->options(collect(AIProvider::cases())->mapWithKeys(fn ($p) => [$p->value => strtoupper($p->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('model_id')
                        ->label(__('ai.field_model_id'))
                        ->required()
                        ->maxLength(100)
                        ->placeholder('gpt-4o-mini'),
                    Forms\Components\TextInput::make('display_name')
                        ->label(__('ai.field_display_name'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder('GPT-4o Mini'),
                    Forms\Components\Textarea::make('description')
                        ->label(__('ai.field_description_en'))
                        ->rows(2)
                        ->maxLength(500),
                    Forms\Components\TextInput::make('api_key_encrypted')
                        ->label(__('ai.field_api_key'))
                        ->password()
                        ->revealable()
                        ->maxLength(500)
                        ->helperText(__('ai.field_api_key_help'))
                        ->dehydrateStateUsing(fn ($state) => $state ?: null)
                        ->dehydrated(fn ($state) => filled($state)),
                ])->columns(2),

            Forms\Components\Section::make(__('ai.section_capabilities'))
                ->schema([
                    Forms\Components\TextInput::make('max_context_tokens')
                        ->label(__('ai.field_max_context_tokens'))
                        ->numeric()
                        ->default(128000),
                    Forms\Components\TextInput::make('max_output_tokens')
                        ->label(__('ai.field_max_output_tokens'))
                        ->numeric()
                        ->default(4096),
                    Forms\Components\Toggle::make('supports_vision')
                        ->label(__('ai.field_supports_vision'))
                        ->default(false),
                    Forms\Components\Toggle::make('supports_json_mode')
                        ->label(__('ai.field_supports_json_mode'))
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make(__('ai.section_pricing'))
                ->schema([
                    Forms\Components\TextInput::make('input_price_per_1m')
                        ->label(__('ai.field_input_price'))
                        ->numeric()
                        ->step(0.01)
                        ->default(0.15)
                        ->prefix('$'),
                    Forms\Components\TextInput::make('output_price_per_1m')
                        ->label(__('ai.field_output_price'))
                        ->numeric()
                        ->step(0.01)
                        ->default(0.60)
                        ->prefix('$'),
                ])->columns(2),

            Forms\Components\Section::make(__('ai.section_status'))
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label(__('ai.field_is_enabled'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_default')
                        ->label(__('ai.field_default_model_flag'))
                        ->default(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('ai.field_sort_order'))
                        ->numeric()
                        ->default(0),
                ])->columns(3),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('ai.section_model_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('provider')
                            ->label(__('ai.field_provider'))
                            ->badge(),
                        Infolists\Components\TextEntry::make('model_id')
                            ->label(__('ai.field_model_id'))
                            ->copyable(),
                        Infolists\Components\TextEntry::make('display_name')
                            ->label(__('ai.field_display_name')),
                        Infolists\Components\TextEntry::make('description')
                            ->label(__('ai.field_description_en'))
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make(__('ai.section_capabilities'))
                    ->schema([
                        Infolists\Components\TextEntry::make('max_context_tokens')
                            ->label(__('ai.field_max_context'))
                            ->numeric(),
                        Infolists\Components\TextEntry::make('max_output_tokens')
                            ->label(__('ai.field_max_output'))
                            ->numeric(),
                        Infolists\Components\IconEntry::make('supports_vision')
                            ->label(__('ai.field_vision'))
                            ->boolean(),
                        Infolists\Components\IconEntry::make('supports_json_mode')
                            ->label(__('ai.field_json_mode'))
                            ->boolean(),
                    ])->columns(4),

                Infolists\Components\Section::make(__('ai.section_pricing'))
                    ->schema([
                        Infolists\Components\TextEntry::make('input_price_per_1m')
                            ->label(__('ai.field_input_1m_short'))
                            ->money('usd'),
                        Infolists\Components\TextEntry::make('output_price_per_1m')
                            ->label(__('ai.field_output_1m_short'))
                            ->money('usd'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->label(__('ai.field_provider'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('ai.field_model'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (AILlmModel $record) => $record->model_id),
                Tables\Columns\TextColumn::make('input_price_per_1m')
                    ->label(__('ai.field_input_1m_short'))
                    ->money('usd')
                    ->sortable(),
                Tables\Columns\TextColumn::make('output_price_per_1m')
                    ->label(__('ai.field_output_1m_short'))
                    ->money('usd')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_context_tokens')
                    ->label(__('ai.field_context'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('supports_vision')
                    ->label(__('ai.field_vision'))
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('supports_json_mode')
                    ->label(__('ai.field_json'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('ai.field_is_enabled'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('ai.field_default'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ai.field_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('ai.field_is_enabled')),
                Tables\Filters\SelectFilter::make('provider')
                    ->options(collect(AIProvider::cases())->mapWithKeys(fn ($p) => [$p->value => strtoupper($p->value)])),
                Tables\Filters\TernaryFilter::make('supports_vision')
                    ->label(__('ai.field_vision')),
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
