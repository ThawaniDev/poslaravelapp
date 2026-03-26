<?php

namespace App\Filament\Resources\StoreSubscriptionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionCreditsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionCredits';

    protected static ?string $title = 'Credits';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amount')
                ->required()
                ->numeric()
                ->prefix('SAR'),

            Forms\Components\TextInput::make('reason')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('granted_by')
                ->default(fn () => auth('admin')->user()?->name)
                ->disabled()
                ->dehydrated(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->money('SAR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(40),

                Tables\Columns\TextColumn::make('granted_by')
                    ->label('Granted By'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Credit'),
            ])
            ->defaultSort('applied_at', 'desc');
    }
}
