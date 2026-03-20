<?php

namespace App\Filament\Resources;

use App\Domain\SystemConfig\Models\AccountingIntegrationConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountingIntegrationConfigResource extends Resource
{
    protected static ?string $model = AccountingIntegrationConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Accounting Configs';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Accounting Configs')->schema([
                Forms\Components\TextInput::make('provider')->required()->maxLength(255),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => AccountingIntegrationConfigResource\Pages\ListAccountingIntegrationConfigs::route('/'),
            'create' => AccountingIntegrationConfigResource\Pages\CreateAccountingIntegrationConfig::route('/create'),
            'edit' => AccountingIntegrationConfigResource\Pages\EditAccountingIntegrationConfig::route('/{record}/edit'),
        ];
    }
}
