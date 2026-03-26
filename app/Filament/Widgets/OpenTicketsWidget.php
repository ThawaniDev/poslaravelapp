<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpenTicketsWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('admin_dashboard.open_support_tickets');
    }

    public static function canView(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['tickets.view', 'tickets.respond', 'tickets.manage']);
    }

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
                    ->label(__('admin_dashboard.ticket_number')),
                TextColumn::make('subject')
                    ->label(__('admin_dashboard.subject'))
                    ->limit(40),
                TextColumn::make('priority')
                    ->label(__('admin_dashboard.priority'))
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label(__('admin_dashboard.status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('admin_dashboard.created'))
                    ->since(),
            ])
            ->paginated(false);
    }
}
