<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Models\AdminIpBlocklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminIpBlocklistResource extends Resource
{
    protected static ?string $model = AdminIpBlocklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_security');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.ip_blocklist');
    }

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.manage_ips']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('security.ip_blocklist'))->schema([
                Forms\Components\TextInput::make('ip_address')
                    ->label(__('security.ip_address'))
                    ->required()
                    ->maxLength(45)
                    ->ip()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('reason')
                    ->label(__('security.reason'))
                    ->maxLength(255)
                    ->placeholder(__('security.block_reason_placeholder')),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label(__('security.expires_at'))
                    ->nullable()
                    ->minDate(now())
                    ->helperText(__('security.expires_at_help')),
                Forms\Components\Hidden::make('blocked_by')
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
                    ->icon('heroicon-o-no-symbol'),
                Tables\Columns\TextColumn::make('reason')
                    ->label(__('security.reason'))
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('blockedBy.name')
                    ->label(__('security.blocked_by')),
                Tables\Columns\TextColumn::make('blocked_at')
                    ->label(__('security.blocked_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('security.expires_at'))
                    ->dateTime()
                    ->placeholder(__('security.never'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_expired')
                    ->label(__('security.expired'))
                    ->state(fn ($record) => $record->isExpired())
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->trueColor('gray')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('security.active_blocks'))
                    ->queries(
                        true: fn ($q) => $q->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        }),
                        false: fn ($q) => $q->where('expires_at', '<=', now()),
                    ),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label(__('security.unblock'))
                    ->before(function ($record) {
                        $admin = auth('admin')->user();
                        AdminActivityLog::record(
                            adminUserId: $admin->id,
                            action: 'remove_ip_blocklist',
                            entityType: 'admin_ip_blocklist',
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
            ->defaultSort('blocked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminIpBlocklistResource\Pages\ListAdminIpBlocklists::route('/'),
            'create' => AdminIpBlocklistResource\Pages\CreateAdminIpBlocklist::route('/create'),
        ];
    }
}
