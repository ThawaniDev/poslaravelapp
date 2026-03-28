<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Enums\SessionStatus;
use App\Domain\Security\Models\AdminSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminSessionResource extends Resource
{
    protected static ?string $model = AdminSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_security');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.active_sessions');
    }

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.manage_sessions']);
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
            Infolists\Components\Section::make(__('security.session_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('adminUser.name')
                        ->label(__('security.admin_user')),
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('security.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof SessionStatus ? $state->label() : $state)
                        ->color(fn ($state) => $state instanceof SessionStatus ? $state->color() : 'gray'),
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label(__('security.ip_address'))
                        ->icon('heroicon-o-globe-alt'),
                    Infolists\Components\TextEntry::make('user_agent')
                        ->label(__('security.user_agent'))
                        ->columnSpanFull(),
                    Infolists\Components\IconEntry::make('two_fa_verified')
                        ->label(__('security.two_fa_verified'))
                        ->boolean(),
                    Infolists\Components\TextEntry::make('started_at')
                        ->label(__('security.started_at'))
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('last_activity_at')
                        ->label(__('security.last_activity'))
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('expires_at')
                        ->label(__('security.expires_at'))
                        ->dateTime()
                        ->placeholder(__('security.never')),
                    Infolists\Components\TextEntry::make('revoked_at')
                        ->label(__('security.revoked_at'))
                        ->dateTime()
                        ->visible(fn ($record) => $record->revoked_at !== null),
                ])->columns(3),
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
                Tables\Columns\TextColumn::make('status')
                    ->label(__('security.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SessionStatus ? $state->label() : ucfirst($state))
                    ->color(fn ($state) => $state instanceof SessionStatus ? $state->color() : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->icon('heroicon-o-globe-alt')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label(__('security.user_agent'))
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->user_agent)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('two_fa_verified')
                    ->label(__('security.two_fa'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('security.started_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label(__('security.last_activity'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('security.status'))
                    ->options(collect(SessionStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),
                Tables\Filters\SelectFilter::make('admin_user_id')
                    ->label(__('security.admin_user'))
                    ->relationship('adminUser', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->label(__('security.revoke_session'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading(__('security.revoke_session'))
                    ->modalDescription(__('security.revoke_session_confirm'))
                    ->action(function ($record) {
                        $admin = auth('admin')->user();
                        $record->revoke();

                        AdminActivityLog::record(
                            adminUserId: $admin->id,
                            action: 'revoke_session',
                            entityType: 'admin_session',
                            entityId: $record->id,
                            details: ['target_admin_id' => $record->admin_user_id],
                        );

                        Notification::make()
                            ->title(__('security.session_revoked'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('last_activity_at', 'desc')
            ->poll('15s');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminSessionResource\Pages\ListAdminSessions::route('/'),
            'view' => AdminSessionResource\Pages\ViewAdminSession::route('/{record}'),
        ];
    }

}
