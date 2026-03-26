<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopPerformingStoresWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('admin_dashboard.top_performing_stores');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Domain\Core\Models\Store::query()
                    ->select([
                        'stores.id',
                        'stores.name',
                        'stores.organization_id',
                        DB::raw('COUNT(transactions.id) as transactions_count'),
                        DB::raw('COALESCE(SUM(transactions.total_amount), 0) as total_gmv'),
                        DB::raw('CASE WHEN COUNT(transactions.id) > 0 THEN COALESCE(SUM(transactions.total_amount), 0) / COUNT(transactions.id) ELSE 0 END as avg_ticket'),
                    ])
                    ->leftJoin('transactions', function ($join) {
                        $join->on('stores.id', '=', 'transactions.store_id')
                            ->where('transactions.status', '=', 'completed')
                            ->where('transactions.created_at', '>=', now()->startOfMonth());
                    })
                    ->with('organization')
                    ->groupBy('stores.id', 'stores.name', 'stores.organization_id')
                    ->orderByDesc('total_gmv')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin_dashboard.store'))
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label(__('admin_dashboard.organization')),
                TextColumn::make('transactions_count')
                    ->label(__('admin_dashboard.transactions'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_gmv')
                    ->label(__('admin_dashboard.gmv'))
                    ->money('SAR')
                    ->sortable(),
                TextColumn::make('avg_ticket')
                    ->label(__('admin_dashboard.avg_ticket'))
                    ->money('SAR')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
