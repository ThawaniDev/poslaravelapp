<?php

namespace App\Filament\Resources;

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

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Languages';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['settings.translations']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Languages')->schema([
                Forms\Components\TextInput::make('locale_code')->required()->maxLength(255),
                Forms\Components\TextInput::make('language_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('language_name_native')->required()->maxLength(255),
                Forms\Components\Select::make('direction')->options(array ('ltr' => 'LTR','rtl' => 'RTL',))->required(),
                Forms\Components\Select::make('calendar_system')->options(array ('gregorian' => 'Gregorian','hijri' => 'Hijri','both' => 'Both',)),
                Forms\Components\Toggle::make('is_default'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('locale_code')->searchable(),
                Tables\Columns\TextColumn::make('language_name'),
                Tables\Columns\TextColumn::make('language_name_native'),
                Tables\Columns\TextColumn::make('direction')->badge(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => SupportedLocaleResource\Pages\ListSupportedLocales::route('/'),
            'create' => SupportedLocaleResource\Pages\CreateSupportedLocale::route('/create'),
            'edit' => SupportedLocaleResource\Pages\EditSupportedLocale::route('/{record}/edit'),
        ];
    }
}
