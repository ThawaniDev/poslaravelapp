<?php

namespace App\Filament\Resources;

use App\Domain\SystemConfig\Models\AgeRestrictedCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgeRestrictedCategoryResource extends Resource
{
    protected static ?string $model = AgeRestrictedCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Age Restrictions';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['settings.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Age Restrictions')->schema([
                Forms\Components\TextInput::make('category_slug')->required()->maxLength(255),
                Forms\Components\TextInput::make('minimum_age')->required()->numeric(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category_slug')->searchable(),
                Tables\Columns\TextColumn::make('minimum_age'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            'index' => AgeRestrictedCategoryResource\Pages\ListAgeRestrictedCategories::route('/'),
            'create' => AgeRestrictedCategoryResource\Pages\CreateAgeRestrictedCategory::route('/create'),
            'edit' => AgeRestrictedCategoryResource\Pages\EditAgeRestrictedCategory::route('/{record}/edit'),
        ];
    }
}
