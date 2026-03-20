<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpenTicketsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Open Support Tickets';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Domain\Support\Models\SupportTicket::query()
                    ->whereIn('status', ['open', 'pending', 'in_progress'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('#'),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(40),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
            ])
            ->paginated(false);
    }
}
