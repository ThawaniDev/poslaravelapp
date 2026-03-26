<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerGroupTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeCustomerGroupTemplates';

    protected static ?string $title = 'Customer Group Templates';

    protected static ?string $icon = 'heroicon-o-user-group';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Name (EN)')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('name_ar')
                ->label('Name (AR)')
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('discount_percentage')
                ->numeric()
                ->step(0.01)
                ->suffix('%')
                ->default(0),
            Forms\Components\TextInput::make('credit_limit')
                ->numeric()
                ->step(0.01)
                ->default(0),
            Forms\Components\TextInput::make('payment_terms_days')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_default_group')
                ->label('Default Group')
                ->default(false),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->name_ar),
                Tables\Columns\TextColumn::make('discount_percentage')->suffix('%'),
                Tables\Columns\TextColumn::make('credit_limit'),
                Tables\Columns\TextColumn::make('payment_terms_days')->label('Terms (days)'),
                Tables\Columns\IconColumn::make('is_default_group')->boolean()->label('Default'),
                Tables\Columns\TextColumn::make('sort_order')->label('Sort')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
