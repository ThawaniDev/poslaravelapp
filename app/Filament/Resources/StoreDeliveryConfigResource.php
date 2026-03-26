<?php

namespace App\Filament\Resources;

use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreDeliveryConfigResource extends Resource
{
    protected static ?string $model = DeliveryPlatformConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'Store Delivery Configs';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Store Delivery Config';

    protected static ?string $pluralModelLabel = 'Store Delivery Configs';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['integrations.view', 'integrations.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('delivery.store_config'))
                ->schema([
                    Forms\Components\TextInput::make('store_id')
                        ->label(__('delivery.store_id'))
                        ->required()
                        ->maxLength(36),
                    Forms\Components\Select::make('platform')
                        ->label(__('delivery.platform'))
                        ->options(fn () => DeliveryPlatform::where('is_active', true)
                            ->orderBy('sort_order')
                            ->pluck('name', 'slug')
                            ->toArray())
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('merchant_id')
                        ->label(__('delivery.merchant_id'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('branch_id_on_platform')
                        ->label(__('delivery.branch_id'))
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make(__('delivery.settings'))
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label(__('delivery.is_enabled')),
                    Forms\Components\Toggle::make('auto_accept')
                        ->label(__('delivery.auto_accept')),
                    Forms\Components\Toggle::make('sync_menu_on_product_change')
                        ->label(__('delivery.sync_menu_on_product_change')),
                    Forms\Components\TextInput::make('throttle_limit')
                        ->label(__('delivery.throttle_limit'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100),
                    Forms\Components\TextInput::make('max_daily_orders')
                        ->label(__('delivery.max_daily_orders'))
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('menu_sync_interval_hours')
                        ->label(__('delivery.menu_sync_interval'))
                        ->numeric()
                        ->suffix('hours'),
                    Forms\Components\Select::make('status')
                        ->label(__('delivery.status'))
                        ->options([
                            'pending' => 'Pending',
                            'active' => 'Active',
                            'suspended' => 'Suspended',
                            'error' => 'Error',
                        ]),
                ])->columns(3),

            Forms\Components\Section::make(__('delivery.activity'))
                ->schema([
                    Forms\Components\Placeholder::make('daily_order_count')
                        ->label(__('delivery.daily_order_count'))
                        ->content(fn (?DeliveryPlatformConfig $record) => $record?->daily_order_count ?? 0),
                    Forms\Components\Placeholder::make('last_order_received_at')
                        ->label(__('delivery.last_order_at'))
                        ->content(fn (?DeliveryPlatformConfig $record) => $record?->last_order_received_at?->diffForHumans() ?? 'Never'),
                    Forms\Components\Placeholder::make('operating_hours_synced')
                        ->label(__('delivery.operating_hours_synced'))
                        ->content(fn (?DeliveryPlatformConfig $record) => $record?->operating_hours_synced ? 'Yes' : 'No'),
                ])->columns(3)
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store_id')
                    ->label(__('delivery.store_id'))
                    ->searchable()
                    ->limit(8)
                    ->tooltip(fn ($record) => $record->store_id),
                Tables\Columns\TextColumn::make('platform')
                    ->label(__('delivery.platform'))
                    ->badge()
                    ->color(fn ($record) => $record->platform->color())
                    ->formatStateUsing(fn ($record) => $record->platform->label()),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('delivery.is_enabled'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('delivery.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('auto_accept')
                    ->label(__('delivery.auto_accept'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('daily_order_count')
                    ->label(__('delivery.today_orders'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_order_received_at')
                    ->label(__('delivery.last_order_at'))
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('delivery.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label(__('delivery.platform'))
                    ->options(fn () => DeliveryPlatform::where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('name', 'slug')
                        ->toArray()),
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('delivery.is_enabled')),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('delivery.status'))
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                        'error' => 'Error',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => StoreDeliveryConfigResource\Pages\ListStoreDeliveryConfigs::route('/'),
            'create' => StoreDeliveryConfigResource\Pages\CreateStoreDeliveryConfig::route('/create'),
            'edit' => StoreDeliveryConfigResource\Pages\EditStoreDeliveryConfig::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_enabled', true)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}
