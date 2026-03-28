<?php

namespace App\Filament\Resources;

use App\Domain\ProviderRegistration\Models\ProviderPermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderPermissionResource extends Resource
{
    protected static ?string $model = ProviderPermission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_people');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.provider_permissions');
    }

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['provider_roles.view', 'provider_roles.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Permission Details'))
                ->description(__('Define a provider-side permission'))
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Permission Name'))
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('Use dot notation: module.action (e.g., inventory.view)'))
                        ->disabled(fn (?ProviderPermission $record): bool => $record !== null),

                    Forms\Components\TextInput::make('group')
                        ->label(__('Group'))
                        ->required()
                        ->maxLength(50)
                        ->helperText(__('Module group (e.g., inventory, sales, reports)')),

                    Forms\Components\Textarea::make('description')
                        ->label(__('Description (EN)'))
                        ->rows(2)
                        ->maxLength(500),

                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('Description (AR)'))
                        ->rows(2)
                        ->maxLength(500),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Active'))
                        ->default(true)
                        ->helperText(__('Inactive permissions are not assignable')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Permission'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('group')
                    ->label(__('Group'))
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),
                Tables\Filters\SelectFilter::make('group')
                    ->label(__('Group'))
                    ->options(fn () => ProviderPermission::query()->distinct()->pluck('group', 'group')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('group')
            ->groups([
                Tables\Grouping\Group::make('group')
                    ->label(__('Group'))
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Permission Details'))
                ->icon('heroicon-o-key')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Name'))->badge()->color('primary'),
                    Infolists\Components\TextEntry::make('group')->label(__('Group'))->badge()->color('info'),
                    Infolists\Components\TextEntry::make('description')->label(__('Description (EN)')),
                    Infolists\Components\TextEntry::make('description_ar')->label(__('Description (AR)')),
                    Infolists\Components\IconEntry::make('is_active')->label(__('Active'))->boolean(),
                ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProviderPermissionResource\Pages\ListProviderPermissions::route('/'),
            'create' => ProviderPermissionResource\Pages\CreateProviderPermission::route('/create'),
            'view' => ProviderPermissionResource\Pages\ViewProviderPermission::route('/{record}'),
            'edit' => ProviderPermissionResource\Pages\EditProviderPermission::route('/{record}/edit'),
        ];
    }
}
