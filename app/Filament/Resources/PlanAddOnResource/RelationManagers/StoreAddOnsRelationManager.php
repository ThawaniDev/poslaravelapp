<?php

namespace App\Filament\Resources\PlanAddOnResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StoreAddOnsRelationManager extends RelationManager
{
    protected static string $relationship = 'storeAddOns';

    protected static ?string $title = 'Store Subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable(),

                Tables\Columns\TextColumn::make('storeSubscription.status')
                    ->label('Subscription Status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
