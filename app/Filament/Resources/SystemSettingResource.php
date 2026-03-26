<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Enums\SystemSettingsGroup;
use App\Domain\SystemConfig\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('settings.system_settings');
    }

    public static function getModelLabel(): string
    {
        return __('settings.system_setting');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.system_settings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.view', 'settings.edit']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.setting_details'))
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label(__('settings.key'))
                        ->disabled()
                        ->maxLength(255),
                    Forms\Components\Select::make('group')
                        ->label(__('settings.group'))
                        ->options(collect(SystemSettingsGroup::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.group_' . $c->value)]))
                        ->disabled()
                        ->native(false),
                    Forms\Components\Textarea::make('description')
                        ->label(__('settings.description'))
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.value'))
                ->schema([
                    Forms\Components\Textarea::make('value')
                        ->label(__('settings.value'))
                        ->required()
                        ->rows(5)
                        ->helperText(__('settings.value_json_helper'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(__('settings.key'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('group')
                    ->label(__('settings.group'))
                    ->formatStateUsing(fn ($state) => $state ? __('settings.group_' . $state->value) : '-')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        SystemSettingsGroup::General => 'primary',
                        SystemSettingsGroup::Payment => 'success',
                        SystemSettingsGroup::Zatca => 'warning',
                        SystemSettingsGroup::Maintenance => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('settings.description'))
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label(__('settings.updated_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label(__('settings.group'))
                    ->options(collect(SystemSettingsGroup::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.group_' . $c->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record) {
                        $record->update(['updated_by' => auth('admin')->id()]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'update_system_setting',
                            entityType: 'system_setting',
                            entityId: $record->id,
                            details: ['key' => $record->key, 'group' => $record->group?->value],
                        );
                    }),
            ])
            ->defaultSort('key', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => SystemSettingResource\Pages\ListSystemSettings::route('/'),
            'edit' => SystemSettingResource\Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
