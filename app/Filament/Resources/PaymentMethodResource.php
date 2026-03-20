<?php

namespace App\Filament\Resources;

use App\Domain\SystemConfig\Models\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payment Methods';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['settings.payment_methods']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Payment Methods')->schema([
                Forms\Components\TextInput::make('method_key')->required()->maxLength(255),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->required()->maxLength(255),
                Forms\Components\Select::make('category')->options(array ('cash' => 'Cash','card' => 'Card','digital' => 'Digital','credit' => 'Credit',))->required(),
                Forms\Components\Toggle::make('requires_terminal'),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('method_key')->searchable(),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('name_ar'),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\IconColumn::make('requires_terminal')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
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
            'index' => PaymentMethodResource\Pages\ListPaymentMethods::route('/'),
            'create' => PaymentMethodResource\Pages\CreatePaymentMethod::route('/create'),
            'edit' => PaymentMethodResource\Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
