<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Models\AdminIpBlocklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
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

    public static function getModelLabel(): string
    {
        return __('security.ip_blocklist_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('security.ip_blocklist');
    }

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = AdminIpBlocklist::where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

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
                    ->unique(ignoreRecord: true)
                    ->helperText(__('security.ip_or_cidr_helper'))
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                if (filter_var($value, FILTER_VALIDATE_IP)) {
                                    return;
                                }
                                if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $value)) {
                                    return;
                                }
                                if (preg_match('/^[0-9a-fA-F:]+\/\d{1,3}$/', $value)) {
                                    return;
                                }
                                $fail(__('security.invalid_ip_or_cidr'));
                            };
                        },
                    ]),
                Forms\Components\Select::make('source')
                    ->label(__('security.source'))
                    ->options([
                        'manual' => __('security.source_manual'),
                        'auto_brute_force' => __('security.source_auto_brute_force'),
                        'import' => __('security.source_import'),
                    ])
                    ->default('manual')
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('reason')
                    ->label(__('security.reason'))
                    ->maxLength(500)
                    ->rows(3)
                    ->placeholder(__('security.block_reason_placeholder'))
                    ->columnSpanFull(),
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('security.ip_blocklist'))
                ->schema([
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label(__('security.ip_address'))
                        ->copyable()
                        ->icon('heroicon-o-no-symbol')
                        ->iconColor('danger'),
                    Infolists\Components\TextEntry::make('source')
                        ->label(__('security.source'))
                        ->badge()
                        ->formatStateUsing(fn (string $state) => __("security.source_{$state}"))
                        ->color(fn (string $state) => match ($state) {
                            'manual' => 'primary',
                            'auto_brute_force' => 'danger',
                            'import' => 'info',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('reason')
                        ->label(__('security.reason'))
                        ->default('-'),
                    Infolists\Components\TextEntry::make('hit_count')
                        ->label(__('security.hit_count'))
                        ->badge()
                        ->color(fn (int $state) => $state > 100 ? 'danger' : ($state > 10 ? 'warning' : 'gray')),
                    Infolists\Components\TextEntry::make('last_hit_at')
                        ->label(__('security.last_hit_at'))
                        ->dateTime()
                        ->placeholder(__('security.never')),
                ])->columns(3),

            Infolists\Components\Section::make(__('security.metadata'))
                ->schema([
                    Infolists\Components\TextEntry::make('blockedBy.name')
                        ->label(__('security.blocked_by')),
                    Infolists\Components\TextEntry::make('blocked_at')
                        ->label(__('security.blocked_at'))
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('expires_at')
                        ->label(__('security.expires_at'))
                        ->dateTime()
                        ->placeholder(__('security.permanent')),
                ])->columns(3),
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
                    ->icon('heroicon-o-no-symbol')
                    ->iconColor('danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('source')
                    ->label(__('security.source'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("security.source_{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        'manual' => 'primary',
                        'auto_brute_force' => 'danger',
                        'import' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')
                    ->label(__('security.reason'))
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason),
                Tables\Columns\TextColumn::make('hit_count')
                    ->label(__('security.hit_count'))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state) => $state > 100 ? 'danger' : ($state > 10 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('blockedBy.name')
                    ->label(__('security.blocked_by'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('blocked_at')
                    ->label(__('security.blocked_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('security.expires_at'))
                    ->dateTime('M d, Y H:i')
                    ->placeholder(__('security.permanent'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_expired')
                    ->label(__('security.expired'))
                    ->state(fn ($record) => $record->isExpired())
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->trueColor('gray')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('last_hit_at')
                    ->label(__('security.last_hit_at'))
                    ->dateTime('M d H:i')
                    ->placeholder(__('security.never'))
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('source')
                    ->label(__('security.source'))
                    ->options([
                        'manual' => __('security.source_manual'),
                        'auto_brute_force' => __('security.source_auto_brute_force'),
                        'import' => __('security.source_import'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset_hits')
                    ->label(__('security.reset_hits'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (AdminIpBlocklist $record) {
                        $record->update(['hit_count' => 0, 'last_hit_at' => null]);
                        Notification::make()->title(__('security.hits_reset'))->success()->send();
                    })
                    ->visible(fn (AdminIpBlocklist $record) => $record->hit_count > 0),
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
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('security.unblock_selected')),
                ]),
            ])
            ->defaultSort('blocked_at', 'desc')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminIpBlocklistResource\Pages\ListAdminIpBlocklists::route('/'),
            'create' => AdminIpBlocklistResource\Pages\CreateAdminIpBlocklist::route('/create'),
            'view' => AdminIpBlocklistResource\Pages\ViewAdminIpBlocklist::route('/{record}'),
            'edit' => AdminIpBlocklistResource\Pages\EditAdminIpBlocklist::route('/{record}/edit'),
        ];
    }
}
