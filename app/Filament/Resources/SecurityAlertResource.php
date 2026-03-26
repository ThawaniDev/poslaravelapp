<?php

namespace App\Filament\Resources;

use App\Domain\Security\Enums\AlertSeverity;
use App\Domain\Security\Enums\SecurityAlertStatus;
use App\Domain\Security\Enums\SecurityAlertType;
use App\Domain\Security\Models\SecurityAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityAlertResource extends Resource
{
    protected static ?string $model = SecurityAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Security Alerts';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.manage_alerts']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('security.resolve_alert'))->schema([
                Forms\Components\Select::make('status')
                    ->label(__('security.status'))
                    ->options(collect(SecurityAlertStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    ))
                    ->required(),
                Forms\Components\Textarea::make('resolution_notes')
                    ->label(__('security.resolution_notes'))
                    ->rows(3),
            ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('security.alert_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('alert_type')
                        ->label(__('security.alert_type'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof SecurityAlertType ? $state->label() : $state)
                        ->color(fn ($state) => $state instanceof SecurityAlertType ? $state->color() : 'gray'),
                    Infolists\Components\TextEntry::make('severity')
                        ->label(__('security.severity'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof AlertSeverity ? $state->label() : $state)
                        ->color(fn ($state) => $state instanceof AlertSeverity ? $state->color() : 'gray'),
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('security.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof SecurityAlertStatus ? $state->label() : $state)
                        ->color(fn ($state) => $state instanceof SecurityAlertStatus ? $state->color() : 'gray'),
                    Infolists\Components\TextEntry::make('description')
                        ->label(__('security.description'))
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('adminUser.name')
                        ->label(__('security.triggered_by')),
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label(__('security.ip_address'))
                        ->icon('heroicon-o-globe-alt'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('security.timestamp'))
                        ->dateTime(),
                ])->columns(3),
            Infolists\Components\Section::make(__('security.resolution'))
                ->schema([
                    Infolists\Components\TextEntry::make('resolvedBy.name')
                        ->label(__('security.resolved_by')),
                    Infolists\Components\TextEntry::make('resolved_at')
                        ->label(__('security.resolved_at'))
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('resolution_notes')
                        ->label(__('security.resolution_notes'))
                        ->columnSpanFull(),
                ])->columns(2)
                ->visible(fn ($record) => $record->isResolved()),
            Infolists\Components\Section::make(__('security.additional_details'))
                ->schema([
                    Infolists\Components\KeyValueEntry::make('details')
                        ->label('')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->visible(fn ($record) => ! empty($record->details)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('alert_type')
                    ->label(__('security.alert_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SecurityAlertType ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof SecurityAlertType ? $state->color() : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('severity')
                    ->label(__('security.severity'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AlertSeverity ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof AlertSeverity ? $state->color() : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('security.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SecurityAlertStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof SecurityAlertStatus ? $state->color() : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('security.description'))
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('adminUser.name')
                    ->label(__('security.admin_user'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('security.timestamp'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('security.status'))
                    ->options(collect(SecurityAlertStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),
                Tables\Filters\SelectFilter::make('severity')
                    ->label(__('security.severity'))
                    ->options(collect(AlertSeverity::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),
                Tables\Filters\SelectFilter::make('alert_type')
                    ->label(__('security.alert_type'))
                    ->options(collect(SecurityAlertType::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('investigate')
                    ->label(__('security.investigate'))
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === SecurityAlertStatus::New)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->startInvestigation();
                        Notification::make()->title(__('security.alert_investigating'))->success()->send();
                    }),
                Tables\Actions\Action::make('resolve')
                    ->label(__('security.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->isResolved())
                    ->form([
                        Forms\Components\Textarea::make('resolution_notes')
                            ->label(__('security.resolution_notes'))
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $admin = auth('admin')->user();
                        $record->resolve($admin->id, $data['resolution_notes'] ?? null);
                        Notification::make()->title(__('security.alert_resolved'))->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => SecurityAlertResource\Pages\ListSecurityAlerts::route('/'),
            'view' => SecurityAlertResource\Pages\ViewSecurityAlert::route('/{record}'),
            'edit' => SecurityAlertResource\Pages\EditSecurityAlert::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SecurityAlert::unresolved()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $critical = SecurityAlert::unresolved()->critical()->count();

        return $critical > 0 ? 'danger' : 'warning';
    }
}
