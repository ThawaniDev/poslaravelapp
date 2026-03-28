<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Billing\Models\HardwareSale;
use App\Domain\Core\Models\Store;
use App\Domain\Hardware\Enums\HardwareSaleItemType;
use App\Domain\ProviderSubscription\Services\BillingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HardwareSaleResource extends Resource
{
    protected static ?string $model = HardwareSale::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_subscription_billing');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.hardware_sales');
    }

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Sale Details'))
                ->schema([
                    Forms\Components\Select::make('store_id')
                        ->label(__('Store'))
                        ->relationship('store', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('sold_by')
                        ->label(__('Sold By'))
                        ->options(fn () => AdminUser::query()->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default(fn () => auth('admin')->id()),

                    Forms\Components\Select::make('item_type')
                        ->options(HardwareSaleItemType::class)
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('item_description')
                        ->maxLength(255)
                        ->helperText('e.g., Sunmi V2 Pro, Epson TM-T88VI'),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Hardware Info'))
                ->schema([
                    Forms\Components\TextInput::make('serial_number')
                        ->maxLength(100)
                        ->prefixIcon('heroicon-m-hashtag'),

                    Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->prefix('SAR'),

                    Forms\Components\DateTimePicker::make('sold_at')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item_type')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'terminal' => 'success',
                        'printer' => 'info',
                        'scanner' => 'warning',
                        'other' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('item_description')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('soldByAdmin.name')
                    ->label(__('Sold By'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sold_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('item_type')
                    ->options(HardwareSaleItemType::class),

                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Store')),

                Tables\Filters\Filter::make('sold_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->where('sold_at', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->where('sold_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_invoice')
                    ->icon('heroicon-m-document-text')
                    ->color('warning')
                    ->label(__('Generate Invoice'))
                    ->requiresConfirmation()
                    ->modalDescription(__('This will generate a new invoice for this hardware sale.'))
                    ->action(function (HardwareSale $record) {
                        $billing = app(BillingService::class);
                        $invoice = $billing->generateHardwareSaleInvoice($record);

                        if ($invoice) {
                            Notification::make()
                                ->title(__('Invoice :number generated', ['number' => $invoice->invoice_number]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('No active subscription found for this store'))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sold_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Sale Details'))
                ->schema([
                    Infolists\Components\TextEntry::make('store.name'),
                    Infolists\Components\TextEntry::make('soldByAdmin.name')->label(__('Sold By')),
                    Infolists\Components\TextEntry::make('item_type')->badge(),
                    Infolists\Components\TextEntry::make('item_description'),
                ])
                ->columns(4),

            Infolists\Components\Section::make(__('Hardware & Payment'))
                ->schema([
                    Infolists\Components\TextEntry::make('serial_number')->copyable()->placeholder(__('N/A')),
                    Infolists\Components\TextEntry::make('amount')->money('SAR'),
                    Infolists\Components\TextEntry::make('sold_at')->dateTime(),
                    Infolists\Components\TextEntry::make('notes')->placeholder(__('No notes'))->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => HardwareSaleResource\Pages\ListHardwareSales::route('/'),
            'create' => HardwareSaleResource\Pages\CreateHardwareSale::route('/create'),
            'view' => HardwareSaleResource\Pages\ViewHardwareSale::route('/{record}'),
            'edit' => HardwareSaleResource\Pages\EditHardwareSale::route('/{record}/edit'),
        ];
    }
}
