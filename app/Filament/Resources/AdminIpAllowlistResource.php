<?php

namespace App\Filament\Resources;

use App\Domain\Security\Models\AdminIpAllowlist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminIpAllowlistResource extends Resource
{
    protected static ?string $model = AdminIpAllowlist::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'IP Allowlist';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.manage_ips']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('IP Allowlist')->schema([
                Forms\Components\TextInput::make('ip_address')->required()->maxLength(255),
                Forms\Components\TextInput::make('label')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')->searchable(),
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => AdminIpAllowlistResource\Pages\ListAdminIpAllowlists::route('/'),
            'create' => AdminIpAllowlistResource\Pages\CreateAdminIpAllowlist::route('/create'),
            'edit' => AdminIpAllowlistResource\Pages\EditAdminIpAllowlist::route('/{record}/edit'),
        ];
    }
}
