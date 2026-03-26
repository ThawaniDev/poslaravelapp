<?php

namespace App\Filament\Resources;

use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\Hardware\Enums\ImplementationFeeStatus;
use App\Domain\Hardware\Enums\ImplementationFeeType;
use App\Domain\ProviderSubscription\Services\BillingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ImplementationFeeResource extends Resource
{
    protected static ?string $model = ImplementationFee::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Subscription & Billing';

    protected static ?string $navigationLabel = 'Implementation Fees';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Fee Details')
                ->schema([
                    Forms\Components\Select::make('store_id')
                        ->label('Store')
                        ->relationship('store', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('fee_type')
                        ->options(ImplementationFeeType::class)
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->prefix('SAR'),

                    Forms\Components\Select::make('status')
                        ->options(ImplementationFeeStatus::class)
                        ->required()
                        ->native(false)
                        ->default('invoiced'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Additional Info')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fee_type')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'setup' => 'info',
                        'training' => 'success',
                        'custom_dev' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'invoiced' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fee_type')
                    ->options(ImplementationFeeType::class),

                Tables\Filters\SelectFilter::make('status')
                    ->options(ImplementationFeeStatus::class),

                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Store'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_paid')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->label('Mark Paid')
                    ->requiresConfirmation()
                    ->visible(fn (ImplementationFee $record) => ($record->status?->value ?? $record->status) === 'invoiced')
                    ->action(function (ImplementationFee $record) {
                        $record->update(['status' => 'paid']);

                        Notification::make()
                            ->title('Fee marked as paid')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('generate_invoice')
                    ->icon('heroicon-m-document-text')
                    ->color('warning')
                    ->label('Generate Invoice')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate a new invoice for this implementation fee.')
                    ->visible(fn (ImplementationFee $record) => ($record->status?->value ?? $record->status) !== 'paid')
                    ->action(function (ImplementationFee $record) {
                        $billing = app(BillingService::class);
                        $invoice = $billing->generateImplementationFeeInvoice($record);

                        if ($invoice) {
                            $record->update(['status' => 'invoiced']);
                            Notification::make()
                                ->title("Invoice {$invoice->invoice_number} generated")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No active subscription found for this store')
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
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Fee Details')
                ->schema([
                    Infolists\Components\TextEntry::make('store.name'),
                    Infolists\Components\TextEntry::make('fee_type')->badge(),
                    Infolists\Components\TextEntry::make('amount')->money('SAR'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Notes')
                ->schema([
                    Infolists\Components\TextEntry::make('notes')->placeholder('No notes'),
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ImplementationFeeResource\Pages\ListImplementationFees::route('/'),
            'create' => ImplementationFeeResource\Pages\CreateImplementationFee::route('/create'),
            'view' => ImplementationFeeResource\Pages\ViewImplementationFee::route('/{record}'),
            'edit' => ImplementationFeeResource\Pages\EditImplementationFee::route('/{record}/edit'),
        ];
    }
}
