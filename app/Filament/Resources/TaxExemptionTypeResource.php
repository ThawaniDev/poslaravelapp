<?php

namespace App\Filament\Resources;

use App\Domain\SystemConfig\Models\TaxExemptionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxExemptionTypeResource extends Resource
{
    protected static ?string $model = TaxExemptionType::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Tax Exemptions';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['settings.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tax Exemptions')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(255),
                Forms\Components\TextInput::make('code')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('name_ar'),
                Tables\Columns\TextColumn::make('code'),
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
            'index' => TaxExemptionTypeResource\Pages\ListTaxExemptionTypes::route('/'),
            'create' => TaxExemptionTypeResource\Pages\CreateTaxExemptionType::route('/create'),
            'edit' => TaxExemptionTypeResource\Pages\EditTaxExemptionType::route('/{record}/edit'),
        ];
    }
}
