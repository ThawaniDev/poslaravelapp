<?php

namespace App\Filament\Resources;

use App\Domain\Subscription\Models\SubscriptionDiscount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionDiscountResource extends Resource
{
    protected static ?string $model = SubscriptionDiscount::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $navigationLabel = 'Discounts';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Discounts')->schema([
                Forms\Components\TextInput::make('code')->required()->maxLength(255),
                Forms\Components\Select::make('discount_type')->options(array ('percentage' => 'Percentage','fixed' => 'Fixed Amount',))->required(),
                Forms\Components\TextInput::make('discount_value')->required()->numeric(),
                Forms\Components\TextInput::make('max_uses')->numeric(),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\DateTimePicker::make('expires_at'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('discount_type')->badge(),
                Tables\Columns\TextColumn::make('discount_value')->sortable(),
                Tables\Columns\TextColumn::make('max_uses'),
                Tables\Columns\TextColumn::make('times_used'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => SubscriptionDiscountResource\Pages\ListSubscriptionDiscounts::route('/'),
            'create' => SubscriptionDiscountResource\Pages\CreateSubscriptionDiscount::route('/create'),
            'edit' => SubscriptionDiscountResource\Pages\EditSubscriptionDiscount::route('/{record}/edit'),
        ];
    }
}
