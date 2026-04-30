<?php

namespace App\Filament\Resources;

use App\Domain\WameedAI\Enums\AIRequestStatus;
use App\Domain\WameedAI\Models\AIUsageLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AIUsageLogResource extends Resource
{
    protected static ?string $model = AIUsageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_usage_logs');
    }

    public static function getModelLabel(): string
    {
        return __('ai.usage_log_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ai.usage_log_label_plural');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.view', 'wameed_ai.manage']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('ai.section_request_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('feature_slug')
                            ->label(__('ai.field_feature'))
                            ->badge(),
                        Infolists\Components\TextEntry::make('store.name')
                            ->label(__('ai.field_store'))
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('store_id')
                            ->label(__('ai.field_store_id'))
                            ->copyable()
                            ->hidden(),
                        Infolists\Components\TextEntry::make('user_id')
                            ->label(__('ai.field_user_id'))
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('ai_feature_definition_id')
                            ->label(__('ai.field_feature_definition_id'))
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('model_used')
                            ->label(__('ai.field_model')),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('ai.field_status'))
                            ->badge()
                            ->color(fn (AIRequestStatus $state): string => match ($state) {
                                AIRequestStatus::SUCCESS => 'success',
                                AIRequestStatus::CACHED => 'info',
                                AIRequestStatus::ERROR => 'danger',
                                AIRequestStatus::RATE_LIMITED => 'warning',
                            }),
                        Infolists\Components\IconEntry::make('response_cached')
                            ->label(__('ai.field_response_cached'))
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('ai.field_created_at'))
                            ->dateTime(),
                    ])->columns(3),

                Infolists\Components\Section::make(__('ai.section_token_cost'))
                    ->schema([
                        Infolists\Components\TextEntry::make('input_tokens')
                            ->label(__('ai.field_input_tokens'))
                            ->numeric(),
                        Infolists\Components\TextEntry::make('output_tokens')
                            ->label(__('ai.field_output_tokens'))
                            ->numeric(),
                        Infolists\Components\TextEntry::make('total_tokens')
                            ->label(__('ai.field_total_tokens'))
                            ->numeric()
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('estimated_cost_usd')
                            ->label(__('ai.field_raw_cost_openai'))
                            ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 4))
                            ->prefix('USD '),
                        Infolists\Components\TextEntry::make('margin_percentage_applied')
                            ->label(__('ai.field_margin_percent'))
                            ->suffix('%')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('billed_cost_usd')
                            ->label(__('ai.field_billed_cost'))
                            ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 4))
                            ->prefix('USD ')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('latency_ms')
                            ->label(__('ai.field_latency'))
                            ->suffix(' ms')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('request_payload_hash')
                            ->label(__('ai.field_payload_hash'))
                            ->copyable()
                            ->placeholder('—'),
                    ])->columns(3),

                Infolists\Components\Section::make(__('ai.section_error_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label(__('ai.field_error_message'))
                            ->columnSpanFull()
                            ->placeholder(__('ai.field_no_errors')),
                    ])
                    ->collapsed()
                    ->visible(fn (AIUsageLog $record): bool => ! empty($record->error_message)),

                Infolists\Components\Section::make(__('ai.section_request_messages'))
                    ->schema([
                        Infolists\Components\TextEntry::make('request_messages')
                            ->label(__('ai.field_messages_sent'))
                            ->columnSpanFull()
                            ->default('Not recorded for this log entry. Newer logs will include the full prompt.')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return 'Not recorded for this log entry. Newer logs will include the full prompt.';
                                $messages = is_string($state) ? json_decode($state, true) : $state;
                                if (!is_array($messages)) return (string) $state;
                                $output = '';
                                foreach ($messages as $msg) {
                                    $role = strtoupper($msg['role'] ?? 'unknown');
                                    $content = $msg['content'] ?? '';
                                    if (is_array($content)) {
                                        $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    }
                                    $output .= "[{$role}]\n{$content}\n\n";
                                }
                                return rtrim($output);
                            })
                            ->prose()
                            ->copyable(),
                    ])
                    ->collapsed(),

                Infolists\Components\Section::make(__('ai.section_metadata'))
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata_json')
                            ->label(__('ai.field_metadata_json'))
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($state ?? '—'))
                            ->prose()
                            ->copyable(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('feature_slug')
                    ->label(__('ai.field_feature'))
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('ai.field_store'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('model_used')
                    ->label(__('ai.field_model'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_tokens')
                    ->label(__('ai.field_total_tokens'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_cost_usd')
                    ->label(__('ai.field_raw_cost_openai'))
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 4))
                    ->sortable()
                    ->description(__('ai.field_openai_price_desc')),
                Tables\Columns\TextColumn::make('margin_percentage_applied')
                    ->label(__('ai.field_margin_percent'))
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('billed_cost_usd')
                    ->label(__('ai.field_billed_cost'))
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 4))
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label(__('ai.field_latency'))
                    ->suffix('ms')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ai.field_status'))
                    ->badge()
                    ->color(fn (AIRequestStatus $state): string => match ($state) {
                        AIRequestStatus::SUCCESS => 'success',
                        AIRequestStatus::CACHED => 'info',
                        AIRequestStatus::ERROR => 'danger',
                        AIRequestStatus::RATE_LIMITED => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('response_cached')
                    ->label(__('ai.field_response_cached'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ai.field_date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AIRequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
                Tables\Filters\SelectFilter::make('feature_slug')
                    ->label(__('ai.field_feature'))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label(__('ai.field_store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('response_cached')
                    ->label(__('ai.field_response_cached')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => AIUsageLogResource\Pages\ListAIUsageLogs::route('/'),
        ];
    }
}
