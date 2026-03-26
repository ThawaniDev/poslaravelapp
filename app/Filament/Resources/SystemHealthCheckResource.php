<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\SystemHealthCheck;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemHealthCheckResource extends Resource
{
    protected static ?string $model = SystemHealthCheck::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('infrastructure.health_checks');
    }

    public static function getModelLabel(): string
    {
        return __('infrastructure.health_check');
    }

    public static function getPluralModelLabel(): string
    {
        return __('infrastructure.health_checks');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['infrastructure.view', 'infrastructure.manage']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service')
                    ->label(__('infrastructure.service'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('infrastructure.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'healthy' => 'success',
                        'warning' => 'warning',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_time_ms')
                    ->label(__('infrastructure.response_time'))
                    ->suffix(' ms')
                    ->sortable()
                    ->color(fn (int $state) => match (true) {
                        $state < 100 => 'success',
                        $state < 500 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label(__('infrastructure.checked_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('infrastructure.status'))
                    ->options([
                        'healthy' => __('infrastructure.healthy'),
                        'warning' => __('infrastructure.warning'),
                        'critical' => __('infrastructure.critical'),
                    ]),
                Tables\Filters\SelectFilter::make('service')
                    ->label(__('infrastructure.service'))
                    ->options(fn () => SystemHealthCheck::query()->select('service')->distinct()->pluck('service', 'service')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('checked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => SystemHealthCheckResource\Pages\ListSystemHealthChecks::route('/'),
        ];
    }
}
