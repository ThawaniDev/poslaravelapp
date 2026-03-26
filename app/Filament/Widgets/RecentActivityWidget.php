<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('admin_dashboard.recent_activity');
    }

    public static function canView(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.view', 'admin_team.view']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Domain\AdminPanel\Models\AdminActivityLog::query()
                    ->with('adminUser')
                    ->latest()
                    ->limit(15)
            )
            ->columns([
                TextColumn::make('adminUser.name')
                    ->label(__('admin_dashboard.admin'))
                    ->limit(20),
                TextColumn::make('action')
                    ->label(__('admin_dashboard.action'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        str_contains($state ?? '', 'delete') => 'danger',
                        str_contains($state ?? '', 'create') => 'success',
                        str_contains($state ?? '', 'update') => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('entity_type')
                    ->label(__('admin_dashboard.entity'))
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                TextColumn::make('ip_address')
                    ->label(__('admin_dashboard.ip_address'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin_dashboard.time'))
                    ->since(),
            ])
            ->paginated(false);
    }
}
