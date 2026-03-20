<?php

namespace App\Filament\Widgets;

use App\Domain\OwnerDashboard\Services\OwnerDashboardService;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockAlertsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('owner_dashboard.filament.low_stock_alerts');
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $storeId = $user?->store_id;

        return $table
            ->query(
                \App\Domain\Inventory\Models\StockLevel::query()
                    ->join('products', 'stock_levels.product_id', '=', 'products.id')
                    ->where('stock_levels.store_id', $storeId ?? 'none')
                    ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.reorder_point')
                    ->where('stock_levels.quantity', '>', 0)
                    ->select([
                        'stock_levels.*',
                        'products.name as product_name',
                        'products.sku',
                    ])
                    ->orderBy('stock_levels.quantity')
            )
            ->columns([
                TextColumn::make('product_name')
                    ->label(__('owner_dashboard.filament.product')),
                TextColumn::make('sku')
                    ->label(__('owner_dashboard.filament.sku')),
                TextColumn::make('quantity')
                    ->label(__('owner_dashboard.filament.stock'))
                    ->color('danger'),
                TextColumn::make('reorder_point')
                    ->label(__('owner_dashboard.filament.reorder')),
            ])
            ->paginated(false)
            ->defaultPaginationPageOption(5);
    }
}
