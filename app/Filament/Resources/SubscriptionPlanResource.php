<?php

namespace App\Filament\Resources;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $navigationLabel = 'Subscription Plans';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.plans', 'billing.view']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Subscription Plans')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(255),
                Forms\Components\TextInput::make('slug')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\TextInput::make('monthly_price')->required()->numeric(),
                Forms\Components\TextInput::make('annual_price')->required()->numeric(),
                Forms\Components\TextInput::make('trial_days')->numeric()->default(14),
                Forms\Components\TextInput::make('grace_period_days')->numeric()->default(7),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Toggle::make('is_highlighted'),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('monthly_price')->sortable()->money('OMR'),
                Tables\Columns\TextColumn::make('annual_price')->money('OMR'),
                Tables\Columns\TextColumn::make('trial_days'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            'index' => SubscriptionPlanResource\Pages\ListSubscriptionPlans::route('/'),
            'create' => SubscriptionPlanResource\Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => SubscriptionPlanResource\Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
