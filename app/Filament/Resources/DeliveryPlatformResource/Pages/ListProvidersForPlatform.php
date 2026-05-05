<?php

namespace App\Filament\Resources\DeliveryPlatformResource\Pages;

use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use App\Filament\Resources\DeliveryPlatformResource;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform Provider Usage View — shows every store using a specific platform.
 * Route: /admin/integrations/platforms/{record}/providers
 */
class ListProvidersForPlatform extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DeliveryPlatformResource::class;

    protected static string $view = 'filament.resources.delivery-platform.list-providers';

    public DeliveryPlatform $record;

    public function mount(string|int $record): void
    {
        $platform = DeliveryPlatform::findOrFail($record);
        $this->record = $platform;
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['integrations.view', 'integrations.manage']),
            403,
        );
    }

    public function getTitle(): string
    {
        return __('delivery.providers_for_platform', ['platform' => $this->record->name]);
    }

    protected function getTableQuery(): Builder
    {
        return DeliveryPlatformConfig::where('platform', $this->record->slug)
            ->with('store');
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('delivery.store_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.organization.name')
                    ->label(__('delivery.organisation'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('delivery.sync_status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'error' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_menu_sync_at')
                    ->label(__('delivery.last_sync_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('delivery.never')),

                Tables\Columns\TextColumn::make('daily_order_count')
                    ->label(__('delivery.daily_order_count'))
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('delivery.is_enabled'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('delivery.sync_status'))
                    ->options([
                        'active' => 'Active',
                        'error' => 'Error',
                        'pending' => 'Pending',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('delivery.is_enabled')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
