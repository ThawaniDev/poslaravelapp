<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Enums\CalendarSystem;
use App\Domain\SystemConfig\Enums\LocaleDirection;
use App\Domain\SystemConfig\Models\SupportedLocale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportedLocaleResource extends Resource
{
    protected static ?string $model = SupportedLocale::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('settings.languages');
    }

    public static function getModelLabel(): string
    {
        return __('settings.locale');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.languages');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.translations']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.locale_details'))
                ->schema([
                    Forms\Components\TextInput::make('locale_code')
                        ->label(__('settings.locale_code'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(10)
                        ->placeholder('ar, en, fr'),
                    Forms\Components\Select::make('direction')
                        ->label(__('settings.direction'))
                        ->options(collect(LocaleDirection::cases())->mapWithKeys(fn ($c) => [$c->value => strtoupper($c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('language_name')
                        ->label(__('settings.language_name'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('language_name_native')
                        ->label(__('settings.language_name_native'))
                        ->required()
                        ->maxLength(100),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.format_settings'))
                ->schema([
                    Forms\Components\Select::make('calendar_system')
                        ->label(__('settings.calendar_system'))
                        ->options(collect(CalendarSystem::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.cal_' . $c->value)]))
                        ->native(false),
                    Forms\Components\TextInput::make('date_format')
                        ->label(__('settings.date_format'))
                        ->maxLength(30)
                        ->placeholder('Y-m-d'),
                    Forms\Components\TextInput::make('number_format')
                        ->label(__('settings.number_format'))
                        ->maxLength(30)
                        ->placeholder('1,000.00'),
                ])->columns(3),

            Forms\Components\Section::make(__('settings.status'))
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('settings.is_active'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_default')
                        ->label(__('settings.is_default'))
                        ->helperText(__('settings.is_default_helper')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('locale_code')
                    ->label(__('settings.locale_code'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('language_name')
                    ->label(__('settings.language_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('language_name_native')
                    ->label(__('settings.language_name_native')),
                Tables\Columns\TextColumn::make('direction')
                    ->label(__('settings.direction'))
                    ->formatStateUsing(fn ($state) => strtoupper($state->value))
                    ->badge()
                    ->color(fn ($state) => $state === LocaleDirection::Rtl ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('calendar_system')
                    ->label(__('settings.calendar_system'))
                    ->formatStateUsing(fn ($state) => $state ? __('settings.cal_' . $state->value) : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('settings.is_active'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('settings.is_default'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('settings.is_active')),
                Tables\Filters\SelectFilter::make('direction')
                    ->label(__('settings.direction'))
                    ->options(collect(LocaleDirection::cases())->mapWithKeys(fn ($c) => [$c->value => strtoupper($c->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('set_default')
                    ->label(__('settings.set_as_default'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => !$record->is_default)
                    ->action(function ($record) {
                        SupportedLocale::where('is_default', true)->update(['is_default' => false]);
                        $record->update(['is_default' => true]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'set_default_locale',
                            entityType: 'supported_locale',
                            entityId: $record->id,
                            details: ['locale_code' => $record->locale_code],
                        );
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => !$record->is_default),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('locale_code', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => SupportedLocaleResource\Pages\ListSupportedLocales::route('/'),
            'create' => SupportedLocaleResource\Pages\CreateSupportedLocale::route('/create'),
            'edit' => SupportedLocaleResource\Pages\EditSupportedLocale::route('/{record}/edit'),
        ];
    }
}
