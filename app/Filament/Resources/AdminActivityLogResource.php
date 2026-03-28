<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminActivityLogResource extends Resource
{
    protected static ?string $model = AdminActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_security');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.audit_log');
    }

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.view']);
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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('security.activity_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('adminUser.name')
                        ->label(__('security.admin_user')),
                    Infolists\Components\TextEntry::make('action')
                        ->label(__('security.action'))
                        ->badge()
                        ->color('primary'),
                    Infolists\Components\TextEntry::make('entity_type')
                        ->label(__('security.entity_type')),
                    Infolists\Components\TextEntry::make('entity_id')
                        ->label(__('security.entity_id'))
                        ->copyable(),
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label(__('security.ip_address'))
                        ->icon('heroicon-o-globe-alt'),
                    Infolists\Components\TextEntry::make('user_agent')
                        ->label(__('security.user_agent'))
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('security.timestamp'))
                        ->dateTime(),
                ])->columns(2),
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
                Tables\Columns\TextColumn::make('adminUser.name')
                    ->label(__('security.admin_user'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label(__('security.action'))
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label(__('security.entity_type'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('entity_id')
                    ->label(__('security.entity_id'))
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->entity_id)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->icon('heroicon-o-globe-alt')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('security.timestamp'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label(__('security.action'))
                    ->options(fn () => AdminActivityLog::distinct()->pluck('action', 'action')->toArray()),
                Tables\Filters\SelectFilter::make('entity_type')
                    ->label(__('security.entity_type'))
                    ->options(fn () => AdminActivityLog::distinct()->pluck('entity_type', 'entity_type')->toArray()),
                Tables\Filters\SelectFilter::make('admin_user_id')
                    ->label(__('security.admin_user'))
                    ->relationship('adminUser', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminActivityLogResource\Pages\ListAdminActivityLogs::route('/'),
            'view' => AdminActivityLogResource\Pages\ViewAdminActivityLog::route('/{record}'),
        ];
    }
}
