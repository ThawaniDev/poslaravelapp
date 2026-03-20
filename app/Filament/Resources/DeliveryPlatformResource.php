<?php

namespace App\Filament\Resources;

use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryPlatformResource extends Resource
{
    protected static ?string $model = DeliveryPlatform::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'Delivery Platforms';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['integrations.view', 'integrations.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Delivery Platforms')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(255),
                Forms\Components\TextInput::make('slug')->required()->maxLength(255),
                Forms\Components\TextInput::make('logo_url')->maxLength(255),
                Forms\Components\Select::make('api_type')->options(array ('rest' => 'REST','webhook' => 'Webhook','polling' => 'Polling',)),
                Forms\Components\TextInput::make('base_url')->maxLength(255),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('api_type')->badge(),
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
            'index' => DeliveryPlatformResource\Pages\ListDeliveryPlatforms::route('/'),
            'create' => DeliveryPlatformResource\Pages\CreateDeliveryPlatform::route('/create'),
            'edit' => DeliveryPlatformResource\Pages\EditDeliveryPlatform::route('/{record}/edit'),
        ];
    }
}
