<?php

namespace App\Filament\Resources;

use App\Domain\PosTerminal\Models\HeldCart;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HeldCartResource extends Resource
{
    protected static ?string $model = HeldCart::class;

    protected static ?string $navigationIcon = 'heroicon-o-pause-circle';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.held_carts');
    }

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['held_carts.view', 'held_carts.manage']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'store:id,name',
                'register:id,name',
                'cashier:id,name',
                'customer:id,name,phone',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('pos.held_carts'))
                ->schema([
                    Infolists\Components\TextEntry::make('label')->label(__('Label'))->placeholder('—'),
                    Infolists\Components\TextEntry::make('store.name')->label(__('Store')),
                    Infolists\Components\TextEntry::make('register.name')->label(__('pos.register')),
                    Infolists\Components\TextEntry::make('cashier.name')->label(__('Cashier')),
                    Infolists\Components\TextEntry::make('customer.name')->label(__('Customer'))->placeholder('—'),
                    Infolists\Components\TextEntry::make('held_at')->label(__('Held At'))->dateTime(),
                    Infolists\Components\TextEntry::make('recalled_at')->label(__('Recalled At'))->dateTime()->placeholder('—'),
                ])
                ->columns(3),

            Infolists\Components\Section::make(__('pos.items'))
                ->schema([
                    Infolists\Components\KeyValueEntry::make('cart_data')
                        ->label('')
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('register.name')
                    ->label(__('pos.register'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('cashier.name')
                    ->label(__('Cashier'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cart_items_count')
                    ->label(__('pos.items'))
                    ->state(fn (HeldCart $record): int => is_array($record->cart_data['items'] ?? null)
                        ? count($record->cart_data['items'])
                        : 0)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('held_at')
                    ->label(__('Held At'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('recalled_at')
                    ->label(__('Recalled At'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label(__('Store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('register_id')
                    ->label(__('pos.register'))
                    ->relationship('register', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('cashier_id')
                    ->label(__('Cashier'))
                    ->relationship('cashier', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('recalled')
                    ->label(__('Recalled'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('recalled_at'),
                        false: fn (Builder $q) => $q->whereNull('recalled_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth('admin')->user()?->hasPermissionTo('held_carts.manage') ?? false)
                    ->successNotification(
                        Notification::make()
                            ->title(__('pos.cart_deleted'))
                            ->success(),
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth('admin')->user()?->hasPermissionTo('held_carts.manage') ?? false),
                ]),
            ])
            ->defaultSort('held_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => HeldCartResource\Pages\ListHeldCarts::route('/'),
            'view' => HeldCartResource\Pages\ViewHeldCart::route('/{record}'),
        ];
    }
}
