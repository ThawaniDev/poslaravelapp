<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Models\AdminIpAllowlist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminIpAllowlistResource extends Resource
{
    protected static ?string $model = AdminIpAllowlist::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'IP Allowlist';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.manage_ips']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('security.ip_allowlist'))->schema([
                Forms\Components\TextInput::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->required()
                    ->maxLength(45)
                    ->ip()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('label')
                    ->label(__('security.label'))
                    ->maxLength(255)
                    ->placeholder(__('security.ip_label_placeholder')),
                Forms\Components\Hidden::make('added_by')
                    ->default(fn () => auth('admin')->id()),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-globe-alt'),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('security.label'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('addedBy.name')
                    ->label(__('security.added_by')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('security.timestamp'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        $admin = auth('admin')->user();
                        AdminActivityLog::record(
                            adminUserId: $admin->id,
                            action: 'remove_ip_allowlist',
                            entityType: 'admin_ip_allowlist',
                            entityId: $record->id,
                            details: ['ip_address' => $record->ip_address],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminIpAllowlistResource\Pages\ListAdminIpAllowlists::route('/'),
            'create' => AdminIpAllowlistResource\Pages\CreateAdminIpAllowlist::route('/create'),
        ];
    }
}
