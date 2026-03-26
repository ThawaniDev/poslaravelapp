<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Models\AdminTrustedDevice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminTrustedDeviceResource extends Resource
{
    protected static ?string $model = AdminTrustedDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Trusted Devices';

    protected static ?int $navigationSort = 6;

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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('security.device_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('adminUser.name')
                        ->label(__('security.admin_user')),
                    Infolists\Components\TextEntry::make('device_name')
                        ->label(__('security.device_name'))
                        ->icon('heroicon-o-device-phone-mobile'),
                    Infolists\Components\TextEntry::make('device_fingerprint')
                        ->label(__('security.device_fingerprint'))
                        ->copyable()
                        ->limit(20),
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label(__('security.ip_address'))
                        ->icon('heroicon-o-globe-alt'),
                    Infolists\Components\TextEntry::make('user_agent')
                        ->label(__('security.user_agent'))
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('trusted_at')
                        ->label(__('security.trusted_at'))
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('last_used_at')
                        ->label(__('security.last_used'))
                        ->dateTime(),
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
                Tables\Columns\TextColumn::make('device_name')
                    ->label(__('security.device_name'))
                    ->searchable()
                    ->icon('heroicon-o-device-phone-mobile'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('trusted_at')
                    ->label(__('security.trusted_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('security.last_used'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('admin_user_id')
                    ->label(__('security.admin_user'))
                    ->relationship('adminUser', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('revoke_trust')
                    ->label(__('security.revoke_trust'))
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('security.revoke_trust'))
                    ->modalDescription(__('security.revoke_trust_confirm'))
                    ->action(function ($record) {
                        $admin = auth('admin')->user();

                        AdminActivityLog::record(
                            adminUserId: $admin->id,
                            action: 'revoke_device_trust',
                            entityType: 'admin_trusted_device',
                            entityId: $record->id,
                            details: [
                                'device_name' => $record->device_name,
                                'target_admin_id' => $record->admin_user_id,
                            ],
                        );

                        $record->delete();

                        Notification::make()
                            ->title(__('security.trust_revoked'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('last_used_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminTrustedDeviceResource\Pages\ListAdminTrustedDevices::route('/'),
            'view' => AdminTrustedDeviceResource\Pages\ViewAdminTrustedDevice::route('/{record}'),
        ];
    }
}
