<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Admin Team';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['admin_team.view', 'admin_team.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Account Details'))
                ->description(__('Basic account information'))
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Full Name'))
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->required()
                        ->email()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('Phone'))
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('password_hash')
                        ->label(__('Password'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation): string => $operation === 'edit' ? __('Leave blank to keep current password') : ''),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Active'))
                        ->default(true)
                        ->helperText(__('Inactive users cannot log in')),
                ])->columns(2),

            Forms\Components\Section::make(__('Roles'))
                ->description(__('Assign roles to determine permissions'))
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Forms\Components\CheckboxList::make('roles')
                        ->label('')
                        ->relationship('roles', 'name')
                        ->descriptions(
                            AdminRole::pluck('description', 'id')->toArray()
                        )
                        ->columns(2)
                        ->gridDirection('row')
                        ->bulkToggleable(),
                ]),

            Forms\Components\Section::make(__('Two-Factor Authentication'))
                ->description(__('2FA status and management'))
                ->icon('heroicon-o-shield-exclamation')
                ->schema([
                    Forms\Components\Placeholder::make('two_factor_status')
                        ->label(__('2FA Status'))
                        ->content(fn (?AdminUser $record): string => $record?->two_factor_enabled
                            ? __('Enabled (confirmed :date)', ['date' => $record->two_factor_confirmed_at?->format('Y-m-d')])
                            : __('Disabled')
                        ),
                ])
                ->visibleOn('edit')
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->color('primary')
                    ->separator(', '),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label(__('2FA'))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('Last Login'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active Status')),
                Tables\Filters\TernaryFilter::make('two_factor_enabled')
                    ->label(__('2FA Enabled')),
                Tables\Filters\SelectFilter::make('roles')
                    ->label(__('Role'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('This will prevent the user from logging in.'))
                        ->visible(fn (AdminUser $record): bool => $record->is_active && $record->id !== auth('admin')->id())
                        ->action(function (AdminUser $record) {
                            $record->update(['is_active' => false]);
                            Notification::make()->title(__('User deactivated'))->success()->send();
                        }),
                    Tables\Actions\Action::make('activate')
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (AdminUser $record): bool => !$record->is_active)
                        ->action(function (AdminUser $record) {
                            $record->update(['is_active' => true]);
                            Notification::make()->title(__('User activated'))->success()->send();
                        }),
                    Tables\Actions\Action::make('reset_2fa')
                        ->label(__('Reset 2FA'))
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('This will disable two-factor authentication for this user.'))
                        ->visible(fn (AdminUser $record): bool => $record->two_factor_enabled)
                        ->action(function (AdminUser $record) {
                            $record->update([
                                'two_factor_secret' => null,
                                'two_factor_enabled' => false,
                                'two_factor_confirmed_at' => null,
                            ]);
                            Notification::make()->title(__('2FA has been reset'))->success()->send();
                        }),
                    Tables\Actions\Action::make('reset_password')
                        ->label(__('Reset Password'))
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('new_password')
                                ->label(__('New Password'))
                                ->password()
                                ->revealable()
                                ->required()
                                ->minLength(8),
                        ])
                        ->visible(fn (AdminUser $record): bool => $record->id !== auth('admin')->id())
                        ->action(function (AdminUser $record, array $data) {
                            $record->update(['password_hash' => Hash::make($data['new_password'])]);
                            Notification::make()->title(__('Password has been reset'))->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deactivate_selected')
                    ->label(__('Deactivate Selected'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $currentUserId = auth('admin')->id();
                        $records->each(function (AdminUser $record) use ($currentUserId) {
                            if ($record->id !== $currentUserId) {
                                $record->update(['is_active' => false]);
                            }
                        });
                        Notification::make()->title(__('Selected users deactivated'))->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Account Details'))
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Name')),
                    Infolists\Components\TextEntry::make('email')->label(__('Email'))->copyable(),
                    Infolists\Components\TextEntry::make('phone')->label(__('Phone')),
                    Infolists\Components\IconEntry::make('is_active')->label(__('Active'))->boolean(),
                    Infolists\Components\TextEntry::make('last_login_at')->label(__('Last Login'))->dateTime(),
                    Infolists\Components\TextEntry::make('last_login_ip')->label(__('Last Login IP'))->badge()->color('gray'),
                ])->columns(3),

            Infolists\Components\Section::make(__('Roles & Permissions'))
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Infolists\Components\TextEntry::make('roles.name')
                        ->label(__('Assigned Roles'))
                        ->badge()
                        ->color('primary'),
                ])->collapsible(),

            Infolists\Components\Section::make(__('Two-Factor Authentication'))
                ->icon('heroicon-o-shield-exclamation')
                ->schema([
                    Infolists\Components\IconEntry::make('two_factor_enabled')
                        ->label(__('2FA Enabled'))
                        ->boolean(),
                    Infolists\Components\TextEntry::make('two_factor_confirmed_at')
                        ->label(__('Confirmed At'))
                        ->dateTime(),
                ])->columns(2)->collapsible(),

            Infolists\Components\Section::make(__('Recent Activity'))
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('adminActivityLogs')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('action')->label(__('Action'))->badge()->color('info'),
                            Infolists\Components\TextEntry::make('entity_type')->label(__('Entity')),
                            Infolists\Components\TextEntry::make('ip_address')->label(__('IP')),
                            Infolists\Components\TextEntry::make('created_at')->label(__('Time'))->dateTime(),
                        ])->columns(4),
                ])->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminUserResource\Pages\ListAdminUsers::route('/'),
            'create' => AdminUserResource\Pages\CreateAdminUser::route('/create'),
            'view' => AdminUserResource\Pages\ViewAdminUser::route('/{record}'),
            'edit' => AdminUserResource\Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }
}
