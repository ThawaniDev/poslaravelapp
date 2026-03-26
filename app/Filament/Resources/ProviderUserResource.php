<?php

namespace App\Filament\Resources;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
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

class ProviderUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Provider Users';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['users.view', 'users.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('User Details'))
                ->description(__('Provider user account details (read-only)'))
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->disabled(),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('Phone'))
                        ->disabled(),

                    Forms\Components\Select::make('role')
                        ->label(__('Role'))
                        ->options(collect(UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->name]))
                        ->disabled(),
                ])->columns(2),

            Forms\Components\Section::make(__('Account Controls'))
                ->description(__('Manage account status'))
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Active'))
                        ->helperText(__('Inactive users cannot log in or use the app')),

                    Forms\Components\Toggle::make('must_change_password')
                        ->label(__('Must Change Password'))
                        ->helperText(__('Force user to change password on next login')),
                ])->columns(2),
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
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label(__('Organization'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Owner => 'danger',
                        UserRole::ChainManager => 'warning',
                        UserRole::BranchManager => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('Last Login'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active Status')),
                Tables\Filters\SelectFilter::make('role')
                    ->label(__('Role'))
                    ->options(collect(UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->name])),
                Tables\Filters\SelectFilter::make('organization')
                    ->label(__('Organization'))
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('store')
                    ->label(__('Store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('toggle_active')
                        ->label(fn (User $record): string => $record->is_active ? __('Deactivate') : __('Activate'))
                        ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->update(['is_active' => !$record->is_active]);
                            $status = $record->is_active ? __('activated') : __('deactivated');
                            Notification::make()->title(__('User :status', ['status' => $status]))->success()->send();
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
                        ->action(function (User $record, array $data) {
                            $record->update([
                                'password_hash' => Hash::make($data['new_password']),
                                'must_change_password' => true,
                            ]);
                            Notification::make()->title(__('Password reset and user must change on next login'))->success()->send();
                        }),
                    Tables\Actions\Action::make('force_password_change')
                        ->label(__('Force Password Change'))
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => !$record->must_change_password)
                        ->action(function (User $record) {
                            $record->update(['must_change_password' => true]);
                            Notification::make()->title(__('User must change password on next login'))->success()->send();
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
                        $records->each(fn (User $r) => $r->update(['is_active' => false]));
                        Notification::make()->title(__('Selected users deactivated'))->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('User Details'))
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Name')),
                    Infolists\Components\TextEntry::make('email')->label(__('Email'))->copyable(),
                    Infolists\Components\TextEntry::make('phone')->label(__('Phone')),
                    Infolists\Components\TextEntry::make('role')->label(__('Role'))->badge(),
                    Infolists\Components\TextEntry::make('locale')->label(__('Locale'))->badge()->color('gray'),
                    Infolists\Components\IconEntry::make('is_active')->label(__('Active'))->boolean(),
                    Infolists\Components\IconEntry::make('must_change_password')->label(__('Must Change Password'))->boolean(),
                ])->columns(3),

            Infolists\Components\Section::make(__('Organization & Store'))
                ->icon('heroicon-o-building-office')
                ->schema([
                    Infolists\Components\TextEntry::make('organization.name')->label(__('Organization')),
                    Infolists\Components\TextEntry::make('store.name')->label(__('Store')),
                ])->columns(2)->collapsible(),

            Infolists\Components\Section::make(__('Login History'))
                ->icon('heroicon-o-clock')
                ->schema([
                    Infolists\Components\TextEntry::make('last_login_at')->label(__('Last Login'))->dateTime(),
                    Infolists\Components\TextEntry::make('last_login_ip')->label(__('Last IP'))->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('email_verified_at')->label(__('Email Verified'))->dateTime(),
                    Infolists\Components\TextEntry::make('created_at')->label(__('Joined'))->dateTime(),
                ])->columns(2)->collapsible(),

            Infolists\Components\Section::make(__('Spatie Roles & Permissions'))
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Infolists\Components\TextEntry::make('roles.name')
                        ->label(__('Assigned Roles'))
                        ->badge()
                        ->color('primary'),
                    Infolists\Components\TextEntry::make('permissions.name')
                        ->label(__('Direct Permissions'))
                        ->badge()
                        ->color('info'),
                ])->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProviderUserResource\Pages\ListProviderUsers::route('/'),
            'view' => ProviderUserResource\Pages\ViewProviderUser::route('/{record}'),
            'edit' => ProviderUserResource\Pages\EditProviderUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['organization', 'store', 'roles']);
    }
}
