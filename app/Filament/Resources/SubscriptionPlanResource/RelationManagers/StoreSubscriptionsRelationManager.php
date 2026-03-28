<?php

namespace App\Filament\Resources\SubscriptionPlanResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables;
use Filament\Tables\Table;

class StoreSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'storeSubscriptions';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Subscribers');
    }

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('Store'))
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
                    ->label(__('Renews'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Subscribed'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('Active'),
                        'trial' => __('Trial'),
                        'grace' => __('Grace'),
                        'cancelled' => __('Cancelled'),
                        'expired' => __('Expired'),
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
