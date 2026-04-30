<?php

namespace App\Filament\Resources;

use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\PosTerminal\Services\TransactionService;
use App\Domain\ZatcaCompliance\Enums\ZatcaComplianceStatus;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.transactions');
    }

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'transaction_number';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['transactions.view', 'transactions.export', 'transactions.void']);
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['transaction_number', 'zatca_uuid'];
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
            Infolists\Components\Section::make(__('pos.transaction'))
                ->schema([
                    Infolists\Components\TextEntry::make('transaction_number')
                        ->label(__('pos.transaction_number'))
                        ->copyable(),
                    Infolists\Components\TextEntry::make('type')
                        ->label(__('Type'))
                        ->badge()
                        ->color(fn (TransactionType $state): string => match ($state) {
                            TransactionType::Sale => 'success',
                            TransactionType::Return => 'warning',
                            TransactionType::Void => 'danger',
                            TransactionType::Exchange => 'info',
                        }),
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->color(fn (TransactionStatus $state): string => match ($state) {
                            TransactionStatus::Completed => 'success',
                            TransactionStatus::Voided => 'danger',
                            TransactionStatus::Pending => 'warning',
                        }),
                    Infolists\Components\TextEntry::make('store.name')->label(__('Store')),
                    Infolists\Components\TextEntry::make('register.name')->label(__('pos.register')),
                    Infolists\Components\TextEntry::make('cashier.name')->label(__('Cashier')),
                    Infolists\Components\TextEntry::make('customer.name')
                        ->label(__('Customer'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('customer.phone')
                        ->label(__('Phone'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('Created'))
                        ->dateTime(),
                ])
                ->columns(3),

            Infolists\Components\Section::make(__('pos.totals'))
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')->label(__('pos.subtotal'))->money('SAR'),
                    Infolists\Components\TextEntry::make('discount_amount')->label(__('pos.discount'))->money('SAR'),
                    Infolists\Components\TextEntry::make('tax_amount')->label(__('pos.tax'))->money('SAR'),
                    Infolists\Components\TextEntry::make('tip_amount')->label(__('pos.tip'))->money('SAR'),
                    Infolists\Components\TextEntry::make('total_amount')
                        ->label(__('pos.total'))
                        ->money('SAR')
                        ->weight('bold'),
                    Infolists\Components\IconEntry::make('is_tax_exempt')
                        ->label(__('Tax Exempt'))
                        ->boolean(),
                ])
                ->columns(3),

            Infolists\Components\Section::make(__('pos.items'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('transactionItems')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')->label(__('Product')),
                            Infolists\Components\TextEntry::make('quantity')->label(__('pos.quantity')),
                            Infolists\Components\TextEntry::make('unit_price')->label(__('pos.unit_price'))->money('SAR'),
                            Infolists\Components\TextEntry::make('discount_amount')->label(__('pos.discount'))->money('SAR'),
                            Infolists\Components\TextEntry::make('tax_amount')->label(__('pos.tax'))->money('SAR'),
                            Infolists\Components\TextEntry::make('line_total')->label(__('pos.line_total'))->money('SAR'),
                        ])
                        ->columns(6)
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Infolists\Components\Section::make(__('pos.payments'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('payments')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('method')->label(__('pos.payment'))->badge(),
                            Infolists\Components\TextEntry::make('amount')->label(__('Amount'))->money('SAR'),
                            Infolists\Components\TextEntry::make('cash_tendered')->label(__('pos.tendered'))->money('SAR')->placeholder('—'),
                            Infolists\Components\TextEntry::make('change_given')->label(__('pos.change'))->money('SAR')->placeholder('—'),
                            Infolists\Components\TextEntry::make('card_brand')->label(__('Card'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('card_last_four')->label(__('Last 4'))->placeholder('—'),
                        ])
                        ->columns(6)
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Infolists\Components\Section::make(__('zatca.nav_group'))
                ->schema([
                    Infolists\Components\TextEntry::make('zatca_status')
                        ->label(__('Status'))
                        ->badge()
                        ->color(fn (?ZatcaComplianceStatus $state): string => match ($state) {
                            ZatcaComplianceStatus::Reported, ZatcaComplianceStatus::Cleared => 'success',
                            ZatcaComplianceStatus::Failed => 'danger',
                            ZatcaComplianceStatus::Pending => 'warning',
                            default => 'gray',
                        })
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('zatca_uuid')->label(__('zatca.uuid'))->copyable()->placeholder('—'),
                    Infolists\Components\TextEntry::make('zatca_hash')->label(__('zatca.hash'))->limit(20)->copyable()->placeholder('—'),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed(),

            Infolists\Components\Section::make(__('Notes'))
                ->schema([
                    Infolists\Components\TextEntry::make('notes')->label('')->placeholder('—')->columnSpanFull(),
                ])
                ->visible(fn (Transaction $record) => filled($record->notes))
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->label(__('pos.transaction_number'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (TransactionType $state): string => match ($state) {
                        TransactionType::Sale => 'success',
                        TransactionType::Return => 'warning',
                        TransactionType::Void => 'danger',
                        TransactionType::Exchange => 'info',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (TransactionStatus $state): string => match ($state) {
                        TransactionStatus::Completed => 'success',
                        TransactionStatus::Voided => 'danger',
                        TransactionStatus::Pending => 'warning',
                    }),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('name', 'ilike', "%{$search}%")
                                ->orWhere('phone', 'ilike', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('pos.total'))
                    ->money('SAR')
                    ->sortable()
                    ->alignRight(),
                Tables\Columns\TextColumn::make('tax_amount')
                    ->label(__('pos.tax'))
                    ->money('SAR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label(__('pos.discount'))
                    ->money('SAR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('zatca_status')
                    ->label(__('zatca.nav_group'))
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(collect(TransactionType::cases())->mapWithKeys(fn ($c) => [$c->value => __(ucfirst($c->value))])->toArray()),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(collect(TransactionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __(ucfirst($c->value))])->toArray()),
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
                Tables\Filters\SelectFilter::make('zatca_status')
                    ->label(__('zatca.nav_group'))
                    ->options(collect(ZatcaComplianceStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->toArray()),
                Tables\Filters\TernaryFilter::make('is_tax_exempt')
                    ->label(__('Tax Exempt')),
                Tables\Filters\TernaryFilter::make('has_customer')
                    ->label(__('Has Customer'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('customer_id'),
                        false: fn (Builder $q) => $q->whereNull('customer_id'),
                    ),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label(__('From')),
                        \Filament\Forms\Components\DatePicker::make('to')->label(__('To')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('void')
                    ->label(__('pos.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Transaction $record): bool => $record->status === TransactionStatus::Completed
                        && $record->type === TransactionType::Sale
                        && (auth('admin')->user()?->hasPermissionTo('transactions.void') ?? false))
                    ->action(function (Transaction $record): void {
                        try {
                            app(TransactionService::class)->void($record, auth('admin')->user());
                            Notification::make()
                                ->title(__('pos.transaction_voided'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => TransactionResource\Pages\ListTransactions::route('/'),
            'view' => TransactionResource\Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
