<?php

namespace App\Filament\Resources;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrderMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'Delivery Orders';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Delivery Order';

    protected static ?string $pluralModelLabel = 'Delivery Orders';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['integrations.view', 'integrations.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('delivery.order_details'))
                ->schema([
                    Forms\Components\TextInput::make('external_order_id')
                        ->label(__('delivery.external_order_id'))
                        ->disabled(),
                    Forms\Components\TextInput::make('platform')
                        ->label(__('delivery.platform'))
                        ->disabled(),
                    Forms\Components\Select::make('delivery_status')
                        ->label(__('delivery.delivery_status'))
                        ->options(
                            collect(DeliveryOrderStatus::cases())
                                ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                                ->toArray()
                        ),
                    Forms\Components\TextInput::make('store_id')
                        ->label(__('delivery.store_id'))
                        ->disabled(),
                ])->columns(2),

            Forms\Components\Section::make(__('delivery.customer_info'))
                ->schema([
                    Forms\Components\TextInput::make('customer_name')
                        ->label(__('delivery.customer_name'))
                        ->disabled(),
                    Forms\Components\TextInput::make('customer_phone')
                        ->label(__('delivery.customer_phone'))
                        ->disabled(),
                    Forms\Components\Textarea::make('delivery_address')
                        ->label(__('delivery.delivery_address'))
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('delivery.notes'))
                        ->disabled()
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make(__('delivery.financials'))
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->label(__('delivery.subtotal'))
                        ->disabled()
                        ->prefix('SAR'),
                    Forms\Components\TextInput::make('delivery_fee')
                        ->label(__('delivery.delivery_fee'))
                        ->disabled()
                        ->prefix('SAR'),
                    Forms\Components\TextInput::make('total_amount')
                        ->label(__('delivery.total_amount'))
                        ->disabled()
                        ->prefix('SAR'),
                    Forms\Components\TextInput::make('items_count')
                        ->label(__('delivery.items_count'))
                        ->disabled(),
                ])->columns(4),

            Forms\Components\Section::make(__('delivery.timestamps'))
                ->schema([
                    Forms\Components\Placeholder::make('accepted_at')
                        ->label(__('delivery.accepted_at'))
                        ->content(fn (?DeliveryOrderMapping $record) => $record?->accepted_at?->format('Y-m-d H:i:s') ?? '-'),
                    Forms\Components\Placeholder::make('ready_at')
                        ->label(__('delivery.ready_at'))
                        ->content(fn (?DeliveryOrderMapping $record) => $record?->ready_at?->format('Y-m-d H:i:s') ?? '-'),
                    Forms\Components\Placeholder::make('dispatched_at')
                        ->label(__('delivery.dispatched_at'))
                        ->content(fn (?DeliveryOrderMapping $record) => $record?->dispatched_at?->format('Y-m-d H:i:s') ?? '-'),
                    Forms\Components\Placeholder::make('delivered_at')
                        ->label(__('delivery.delivered_at'))
                        ->content(fn (?DeliveryOrderMapping $record) => $record?->delivered_at?->format('Y-m-d H:i:s') ?? '-'),
                ])->columns(4)
                ->visibleOn('view'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('external_order_id')
                    ->label(__('delivery.external_order_id'))
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('platform')
                    ->label(__('delivery.platform'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DeliveryConfigPlatform ? $state->label() : (DeliveryConfigPlatform::tryFrom($state)?->label() ?? $state))
                    ->color(fn ($state) => $state instanceof DeliveryConfigPlatform ? $state->color() : (DeliveryConfigPlatform::tryFrom($state)?->color() ?? 'gray')),
                Tables\Columns\TextColumn::make('delivery_status')
                    ->label(__('delivery.delivery_status'))
                    ->badge()
                    ->color(fn ($state) => $state instanceof DeliveryOrderStatus ? $state->color() : (DeliveryOrderStatus::tryFrom($state)?->color() ?? 'gray'))
                    ->formatStateUsing(fn ($state) => $state instanceof DeliveryOrderStatus ? $state->label() : (DeliveryOrderStatus::tryFrom($state)?->label() ?? $state)),
                Tables\Columns\TextColumn::make('store_id')
                    ->label(__('delivery.store_id'))
                    ->searchable()
                    ->limit(8)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('delivery.customer_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('delivery.total_amount'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('delivery.items_count'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('delivery.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label(__('delivery.platform'))
                    ->options(
                        collect(DeliveryConfigPlatform::cases())
                            ->mapWithKeys(fn ($p) => [$p->value => $p->label()])
                            ->toArray()
                    ),
                Tables\Filters\SelectFilter::make('delivery_status')
                    ->label(__('delivery.delivery_status'))
                    ->options(
                        collect(DeliveryOrderStatus::cases())
                            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                            ->toArray()
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => DeliveryOrderResource\Pages\ListDeliveryOrders::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('delivery_status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $count = static::getModel()::where('delivery_status', 'pending')->count();

        return $count > 0 ? 'warning' : 'gray';
    }
}
