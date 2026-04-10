<?php

namespace App\Filament\Resources;

use App\Domain\WameedAI\Enums\AIRequestStatus;
use App\Domain\WameedAI\Models\AIUsageLog;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('feature_slug')
                    ->label('Feature')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('store_id')
                    ->label('Store')
                    ->searchable()
                    ->limit(8)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('model_used')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_tokens')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_cost_usd')
                    ->label('Cost (USD)')
                    ->money('usd')
                    ->sortable(),
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
