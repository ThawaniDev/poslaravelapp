<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentStoresWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('admin_dashboard.recent_stores');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Domain\Core\Models\Store::query()
                    ->with(['organization.subscription.subscriptionPlan'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin_dashboard.store'))
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label(__('admin_dashboard.organization')),
                TextColumn::make('city')
                    ->label(__('admin_dashboard.city')),
                TextColumn::make('organization.subscription.subscriptionPlan.name')
                    ->label(__('admin_dashboard.plan'))
                    ->badge()
                    ->color('primary'),
                TextColumn::make('is_active')
                    ->label(__('admin_dashboard.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? __('admin_dashboard.status_active') : __('admin_dashboard.status_inactive'))
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                TextColumn::make('created_at')
                    ->label(__('admin_dashboard.registered'))
                    ->since(),
            ])
            ->paginated(false);
    }
}
