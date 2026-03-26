<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminRoleResource extends Resource
{
    protected static ?string $model = AdminRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Admin Roles';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['admin_team.roles']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Role Details'))
                ->description(__('Define the role name and description'))
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Role Name'))
                        ->required()
                        ->maxLength(100)
                        ->disabled(fn (?AdminRole $record): bool => $record?->is_system ?? false)
                        ->helperText(fn (?AdminRole $record): string => $record?->is_system ? __('System roles cannot be renamed') : ''),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('Slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?AdminRole $record): bool => $record !== null)
                        ->dehydrated()
                        ->helperText(__('URL-safe identifier (auto-generated if empty)'))
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if ($state) {
                                $set('slug', \Illuminate\Support\Str::slug($state, '_'));
                            }
                        }),

                    Forms\Components\Textarea::make('description')
                        ->label(__('Description'))
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_system')
                        ->label(__('System Role'))
                        ->helperText(__('System roles cannot be deleted'))
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                ])->columns(2),

            Forms\Components\Section::make(__('Permissions'))
                ->description(__('Select which permissions this role grants'))
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('')
                        ->relationship('permissions', 'name')
                        ->options(function () {
                            return \Illuminate\Support\Facades\Cache::store('array')
                                ->rememberForever('admin_permissions_all', fn () => AdminPermission::query()
                                    ->orderBy('group')->orderBy('name')->get())
                                ->mapWithKeys(fn ($p) => [$p->id => $p->name]);
                        })
                        ->descriptions(function () {
                            return \Illuminate\Support\Facades\Cache::store('array')
                                ->rememberForever('admin_permissions_all', fn () => AdminPermission::query()
                                    ->orderBy('group')->orderBy('name')->get())
                                ->mapWithKeys(fn ($p) => [$p->id => $p->description]);
                        })
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row'),
                ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Role'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn (AdminRole $record): string => $record->is_system ? 'heroicon-o-lock-closed' : 'heroicon-o-shield-check'),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('Permissions'))
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('Users'))
                    ->counts('users')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('System'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_system')
                    ->label(__('System Roles')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (AdminRole $record): bool => !$record->is_system || auth('admin')->user()?->isSuperAdmin()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AdminRole $record): bool => !$record->is_system)
                    ->before(function (AdminRole $record) {
                        if ($record->users()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('Cannot delete'))
                                ->body(__('This role has assigned users. Reassign them first.'))
                                ->danger()
                                ->send();

                            return false;
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('is_system', 'desc')
            ->reorderable(false);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Role Details'))
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Role Name')),
                    Infolists\Components\TextEntry::make('slug')->label(__('Slug'))->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull(),
                    Infolists\Components\IconEntry::make('is_system')->label(__('System Role'))->boolean(),
                ])->columns(3),

            Infolists\Components\Section::make(__('Assigned Permissions'))
                ->icon('heroicon-o-key')
                ->schema([
                    Infolists\Components\TextEntry::make('permissions.name')
                        ->label('')
                        ->badge()
                        ->color('info')
                        ->separator(', '),
                ])->collapsible(),

            Infolists\Components\Section::make(__('Assigned Users'))
                ->icon('heroicon-o-users')
                ->schema([
                    Infolists\Components\TextEntry::make('users.name')
                        ->label('')
                        ->badge()
                        ->color('success')
                        ->separator(', '),
                ])->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AdminRoleResource\Pages\ListAdminRoles::route('/'),
            'create' => AdminRoleResource\Pages\CreateAdminRole::route('/create'),
            'view' => AdminRoleResource\Pages\ViewAdminRole::route('/{record}'),
            'edit' => AdminRoleResource\Pages\EditAdminRole::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['permissions', 'users']);
    }
}
