<?php

namespace App\Filament\Resources;

use App\Domain\SystemConfig\Models\CertifiedHardware;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CertifiedHardwareResource extends Resource
{
    protected static ?string $model = CertifiedHardware::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Hardware Catalog';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['settings.hardware_catalog']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Hardware Catalog')->schema([
                Forms\Components\TextInput::make('brand')->required()->maxLength(255),
                Forms\Components\TextInput::make('model')->required()->maxLength(255),
                Forms\Components\Select::make('device_type')->options(array ('terminal' => 'Terminal','printer' => 'Printer','scanner' => 'Scanner','display' => 'Display','scale' => 'Scale','cash_drawer' => 'Cash Drawer',))->required(),
                Forms\Components\TextInput::make('connection_types')->maxLength(255),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('notes')->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand')->searchable(),
                Tables\Columns\TextColumn::make('model')->searchable(),
                Tables\Columns\TextColumn::make('device_type')->badge(),
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
            'index' => CertifiedHardwareResource\Pages\ListCertifiedHardware::route('/'),
            'create' => CertifiedHardwareResource\Pages\CreateCertifiedHardware::route('/create'),
            'edit' => CertifiedHardwareResource\Pages\EditCertifiedHardware::route('/{record}/edit'),
        ];
    }
}
