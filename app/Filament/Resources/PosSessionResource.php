<?php

namespace App\Filament\Resources;

use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\PosTerminal\Models\PosSession;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosSessionResource extends Resource
{
    protected static ?string $model = PosSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.pos_sessions');
    }

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['pos_sessions.view', 'pos_sessions.manage']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
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
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('pos.shift'))
                ->schema([
                    Infolists\Components\TextEntry::make('store.name')
                        ->label(__('Store')),
                    Infolists\Components\TextEntry::make('register.name')
                        ->label(__('pos.register')),
                    Infolists\Components\TextEntry::make('cashier.name')
                        ->label(__('Cashier')),
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->color(fn (CashSessionStatus $state): string => match ($state) {
                            CashSessionStatus::Open => 'success',
                            CashSessionStatus::Closed => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('opened_at')
                        ->label(__('Opened At'))
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('closed_at')
                        ->label(__('Closed At'))
                        ->dateTime()
                        ->placeholder('—'),
                ])
                ->columns(3),

            Infolists\Components\Section::make(__('pos.cash'))
                ->schema([
                    Infolists\Components\TextEntry::make('opening_cash')
                        ->label(__('pos.opening_cash'))
                        ->money('SAR'),
                    Infolists\Components\TextEntry::make('expected_cash')
                        ->label(__('pos.expected_cash'))
                        ->money('SAR'),
                    Infolists\Components\TextEntry::make('closing_cash')
                        ->label(__('pos.closing_cash'))
                        ->money('SAR')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('cash_difference')
                        ->label(__('pos.cash_difference'))
                        ->money('SAR')
                        ->color(fn ($state) => is_null($state) ? 'gray' : ((float) $state == 0.0 ? 'success' : 'danger')),
                ])
                ->columns(4),

            Infolists\Components\Section::make(__('pos.sales_summary'))
                ->schema([
                    Infolists\Components\TextEntry::make('total_cash_sales')->label(__('pos.cash'))->money('SAR'),
                    Infolists\Components\TextEntry::make('total_card_sales')->label(__('pos.card'))->money('SAR'),
                    Infolists\Components\TextEntry::make('total_other_sales')->label(__('pos.other'))->money('SAR'),
                    Infolists\Components\TextEntry::make('total_refunds')->label(__('pos.return'))->money('SAR'),
                    Infolists\Components\TextEntry::make('total_voids')->label(__('pos.void'))->money('SAR'),
                    Infolists\Components\TextEntry::make('transaction_count')->label(__('pos.transaction_count')),
                    Infolists\Components\IconEntry::make('z_report_printed')
                        ->label(__('Z-Report'))
                        ->boolean(),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (CashSessionStatus $state): string => match ($state) {
                        CashSessionStatus::Open => 'success',
                        CashSessionStatus::Closed => 'gray',
                    }),
                Tables\Columns\TextColumn::make('opening_cash')
                    ->label(__('pos.opening_cash'))
                    ->money('SAR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expected_cash')
                    ->label(__('pos.expected_cash'))
                    ->money('SAR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('closing_cash')
                    ->label(__('pos.closing_cash'))
                    ->money('SAR')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('cash_difference')
                    ->label(__('pos.cash_difference'))
                    ->money('SAR')
                    ->sortable()
                    ->color(fn ($state) => is_null($state) ? 'gray' : ((float) $state == 0.0 ? 'success' : 'danger')),
                Tables\Columns\TextColumn::make('transaction_count')
                    ->label(__('pos.transaction_count'))
                    ->sortable()
                    ->alignRight(),
                Tables\Columns\IconColumn::make('z_report_printed')
                    ->label('Z')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('opened_at')
                    ->label(__('Opened At'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('closed_at')
                    ->label(__('Closed At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        CashSessionStatus::Open->value => __('Open'),
                        CashSessionStatus::Closed->value => __('Closed'),
                    ]),
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
                Tables\Filters\Filter::make('opened_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label(__('From')),
                        \Filament\Forms\Components\DatePicker::make('to')->label(__('To')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('opened_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('opened_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('forceClose')
                    ->label(__('Force Close'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('pos.force_close_warning'))
                    ->visible(fn (PosSession $record): bool => $record->status === CashSessionStatus::Open
                        && (auth('admin')->user()?->hasPermissionTo('pos_sessions.manage') ?? false))
                    ->action(function (PosSession $record): void {
                        $record->update([
                            'status' => CashSessionStatus::Closed,
                            'closed_at' => now(),
                            'closing_cash' => $record->expected_cash,
                            'cash_difference' => 0,
                        ]);
                        Notification::make()
                            ->title(__('pos.session_closed'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('opened_at', 'desc')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => PosSessionResource\Pages\ListPosSessions::route('/'),
            'view' => PosSessionResource\Pages\ViewPosSession::route('/{record}'),
        ];
    }
}
