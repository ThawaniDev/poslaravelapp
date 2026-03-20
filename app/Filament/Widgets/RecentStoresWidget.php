<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentStoresWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Recent Store Registrations';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Domain\Core\Models\Store::query()
                    ->with('organization')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Store')
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label('Organization'),
                TextColumn::make('city')
                    ->label('City'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->since(),
            ])
            ->paginated(false);
    }
}
