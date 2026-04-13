<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Security\Models\AdminIpAllowlist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminIpAllowlistResource extends Resource
{
    protected static ?string $model = AdminIpAllowlist::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_security');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.ip_allowlist');
    }

    public static function getModelLabel(): string
    {
        return __('security.ip_allowlist_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('security.ip_allowlist');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = AdminIpAllowlist::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

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
                Forms\Components\TextInput::make('label')
                    ->label(__('security.label'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('security.ip_label_placeholder')),
                Forms\Components\Textarea::make('description')
                    ->label(__('security.description'))
                    ->maxLength(500)
                    ->rows(3)
                    ->placeholder(__('security.allowlist_description_placeholder'))
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label(__('security.expires_at'))
                    ->nullable()
                    ->minDate(now())
                    ->helperText(__('security.expires_at_help')),
                Forms\Components\Hidden::make('added_by')
                    ->default(fn () => auth('admin')->id()),
            ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('security.ip_allowlist'))
                ->schema([
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label(__('security.ip_address'))
                        ->copyable()
                        ->icon('heroicon-o-globe-alt')
                        ->iconColor('success'),
                    Infolists\Components\TextEntry::make('label')
                        ->label(__('security.label')),
                    Infolists\Components\TextEntry::make('description')
                        ->label(__('security.description'))
                        ->default('-'),
                    Infolists\Components\TextEntry::make('last_used_at')
                        ->label(__('security.last_used'))
                        ->dateTime()
                        ->placeholder(__('security.never')),
                ])->columns(2),

            Infolists\Components\Section::make(__('security.metadata'))
                ->schema([
                    Infolists\Components\TextEntry::make('addedBy.name')
                        ->label(__('security.added_by')),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('security.timestamp'))
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
                    ->icon('heroicon-o-globe-alt')
                    ->iconColor('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('security.label'))
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('security.description'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('addedBy.name')
                    ->label(__('security.added_by'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('security.timestamp'))
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
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('security.last_used'))
                    ->dateTime('M d H:i')
                    ->placeholder(__('security.never'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('security.active_entries'))
                    ->queries(
                        true: fn ($q) => $q->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        }),
                        false: fn ($q) => $q->where('expires_at', '<=', now()),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('security.remove_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminIpAllowlistResource\Pages\ListAdminIpAllowlists::route('/'),
            'create' => AdminIpAllowlistResource\Pages\CreateAdminIpAllowlist::route('/create'),
            'view' => AdminIpAllowlistResource\Pages\ViewAdminIpAllowlist::route('/{record}'),
            'edit' => AdminIpAllowlistResource\Pages\EditAdminIpAllowlist::route('/{record}/edit'),
        ];
    }
}
