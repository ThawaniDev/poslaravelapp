<?php

namespace App\Filament\Resources\SubscriptionPlanResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StoreSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'storeSubscriptions';

    protected static ?string $title = 'Subscribers';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'grace' => 'warning',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label('Renews')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Subscribed')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'trial' => 'Trial',
                        'grace' => 'Grace',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
