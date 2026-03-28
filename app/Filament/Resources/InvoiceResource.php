<?php

namespace App\Filament\Resources;

use App\Domain\ProviderSubscription\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_subscription_billing');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.invoices');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.invoices', 'billing.view']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Invoice Details'))
                ->description(__('Invoice header information'))
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->disabled(fn (?Invoice $record) => $record !== null),
                        Forms\Components\Select::make('store_subscription_id')
                            ->relationship('storeSubscription', 'id', fn (Builder $query) => $query->with('store'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->store?->name . ' — ' . $record->id)
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label(__('Subtotal (SAR)'))
                            ->numeric()
                            ->prefix('SAR')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('total', round(((float) ($state ?? 0)) * 1.15, 2))),
                        Forms\Components\TextInput::make('tax')
                            ->label(__('Tax / VAT (SAR)'))
                            ->numeric()
                            ->prefix('SAR')
                            ->default(0),
                        Forms\Components\TextInput::make('total')
                            ->label(__('Total (SAR)'))
                            ->numeric()
                            ->prefix('SAR')
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => __('Draft'),
                                'pending' => __('Pending'),
                                'paid' => __('Paid'),
                                'failed' => __('Failed'),
                                'refunded' => __('Refunded'),
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\DatePicker::make('due_date')
                            ->required(),
                        Forms\Components\DateTimePicker::make('paid_at'),
                    ]),
                    Forms\Components\TextInput::make('pdf_url')
                        ->label(__('PDF URL'))
                        ->url()
                        ->maxLength(500),
                ]),
            Forms\Components\Section::make(__('Line Items'))
                ->description(__('Invoice line item breakdown'))
                ->schema([
                    Forms\Components\Repeater::make('invoiceLineItems')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),
                                Forms\Components\TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('SAR')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                                        $qty = (int) ($get('quantity') ?? 1);
                                        $set('total', round(((float) ($state ?? 0)) * $qty, 2));
                                    }),
                            ]),
                            Forms\Components\TextInput::make('total')
                                ->numeric()
                                ->prefix('SAR')
                                ->disabled()
                                ->dehydrated(),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel(__('Add Line Item'))
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'New Item'),
                ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('storeSubscription.store.name')
                    ->label(__('Store'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('storeSubscription.subscriptionPlan.name')
                    ->label(__('Plan'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Subtotal'))
                    ->money('SAR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tax')
                    ->money('SAR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total')
                    ->money('SAR')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Invoice $record) => ($record->status?->value ?? $record->status) === 'pending' && $record->due_date && $record->due_date->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => __('Draft'),
                        'pending' => __('Pending'),
                        'paid' => __('Paid'),
                        'failed' => __('Failed'),
                        'refunded' => __('Refunded'),
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('overdue')
                    ->label(__('Overdue'))
                    ->query(fn (Builder $query) => $query
                        ->where('status', 'pending')
                        ->whereNotNull('due_date')
                        ->where('due_date', '<', now())
                    ),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('mark_paid')
                        ->label(__('Mark as Paid'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Invoice $record) => ($record->status?->value ?? $record->status) === 'pending' && auth('admin')->user()?->hasPermission('billing.edit'))
                        ->requiresConfirmation()
                        ->action(function (Invoice $record) {
                            $record->update(['status' => 'paid', 'paid_at' => now()]);
                            Notification::make()->title(__('Invoice marked as paid'))->success()->send();
                        }),
                    Tables\Actions\Action::make('refund')
                        ->label(__('Process Refund'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Invoice $record) => ($record->status?->value ?? $record->status) === 'paid' && auth('admin')->user()?->hasPermission('billing.refund'))
                        ->requiresConfirmation()
                        ->modalDescription(__('This will mark the invoice as refunded. This action cannot be undone.'))
                        ->action(function (Invoice $record) {
                            $record->update(['status' => 'refunded']);
                            Notification::make()->title(__('Invoice refunded'))->warning()->send();
                        }),
                    Tables\Actions\Action::make('download_pdf')
                        ->label(__('Download PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn (Invoice $record) => $record->pdf_url !== null)
                        ->url(fn (Invoice $record) => $record->pdf_url, shouldOpenInNewTab: true),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Invoice Details'))
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->copyable()
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('storeSubscription.store.name')->label(__('Store')),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state): string => match ($state?->value ?? $state) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                'refunded' => 'info',
                                'draft' => 'gray',
                                default => 'gray',
                            }),
                    ]),
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('amount')->money('SAR')->label(__('Subtotal')),
                        Infolists\Components\TextEntry::make('tax')->money('SAR')->label(__('VAT')),
                        Infolists\Components\TextEntry::make('total')->money('SAR')->weight('bold'),
                        Infolists\Components\TextEntry::make('due_date')->date(),
                    ]),
                    Infolists\Components\TextEntry::make('paid_at')->dateTime()->placeholder(__('Not paid yet')),
                ]),
            Infolists\Components\Section::make(__('Line Items'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('invoiceLineItems')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('description'),
                            Infolists\Components\TextEntry::make('quantity'),
                            Infolists\Components\TextEntry::make('unit_price')->money('SAR'),
                            Infolists\Components\TextEntry::make('total')->money('SAR')->weight('bold'),
                        ])
                        ->columns(4),
                ])->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => InvoiceResource\Pages\ListInvoices::route('/'),
            'create' => InvoiceResource\Pages\CreateInvoice::route('/create'),
            'view' => InvoiceResource\Pages\ViewInvoice::route('/{record}'),
            'edit' => InvoiceResource\Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
