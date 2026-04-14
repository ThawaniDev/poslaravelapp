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
        return 'AI Usage Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'AI Usage Logs';
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
                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('feature_slug')
                            ->label('Feature')
                            ->badge(),
                        Infolists\Components\TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('store_id')
                            ->label('Store ID')
                            ->copyable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        Infolists\Components\TextEntry::make('user_id')
                            ->label('User ID')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('ai_feature_definition_id')
                            ->label('Feature Definition ID')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('model_used')
                            ->label('Model'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (AIRequestStatus $state): string => match ($state) {
                                AIRequestStatus::SUCCESS => 'success',
                                AIRequestStatus::CACHED => 'info',
                                AIRequestStatus::ERROR => 'danger',
                                AIRequestStatus::RATE_LIMITED => 'warning',
                            }),
                        Infolists\Components\IconEntry::make('response_cached')
                            ->label('Cached')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])->columns(3),

                Infolists\Components\Section::make('Token Usage & Cost')
                    ->schema([
                        Infolists\Components\TextEntry::make('input_tokens')
                            ->label('Input Tokens')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('output_tokens')
                            ->label('Output Tokens')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('total_tokens')
                            ->label('Total Tokens')
                            ->numeric()
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('estimated_cost_usd')
                            ->label('Raw Cost (OpenAI)')
                            ->money('usd'),
                        Infolists\Components\TextEntry::make('margin_percentage_applied')
                            ->label('Margin %')
                            ->suffix('%')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('billed_cost_usd')
                            ->label('Billed Cost')
                            ->money('usd')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('latency_ms')
                            ->label('Latency')
                            ->suffix(' ms')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('request_payload_hash')
                            ->label('Payload Hash')
                            ->copyable()
                            ->placeholder('—'),
                    ])->columns(3),

                Infolists\Components\Section::make('Error Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error Message')
                            ->columnSpanFull()
                            ->placeholder('No errors'),
                    ])
                    ->collapsed()
                    ->visible(fn (AIUsageLog $record): bool => ! empty($record->error_message)),

                Infolists\Components\Section::make('Request Messages (Prompt)')
                    ->schema([
                        Infolists\Components\TextEntry::make('request_messages')
                            ->label('Messages Sent to Model')
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return '—';
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

                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata_json')
                            ->label('Metadata (JSON)')
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
                    ->label('Feature')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('model_used')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_tokens')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_cost_usd')
                    ->label('Raw Cost')
                    ->money('usd')
                    ->sortable()
                    ->description('OpenAI price'),
                Tables\Columns\TextColumn::make('margin_percentage_applied')
                    ->label('Margin')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('billed_cost_usd')
                    ->label('Billed Cost')
                    ->money('usd')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('Latency')
                    ->suffix('ms')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (AIRequestStatus $state): string => match ($state) {
                        AIRequestStatus::SUCCESS => 'success',
                        AIRequestStatus::CACHED => 'info',
                        AIRequestStatus::ERROR => 'danger',
                        AIRequestStatus::RATE_LIMITED => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('response_cached')
                    ->label('Cached')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AIRequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
                Tables\Filters\SelectFilter::make('feature_slug')
                    ->label('Feature')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('response_cached')
                    ->label('Cached'),
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
