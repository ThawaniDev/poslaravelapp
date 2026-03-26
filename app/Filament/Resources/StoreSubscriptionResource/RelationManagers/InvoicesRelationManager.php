<?php

namespace App\Filament\Resources\StoreSubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('tax')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('total')
                    ->money('SAR')
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
                    }),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                        'draft' => 'Draft',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ])),
                Tables\Actions\Action::make('view_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn ($record) => $record->pdf_url !== null)
                    ->url(fn ($record) => $record->pdf_url, shouldOpenInNewTab: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
