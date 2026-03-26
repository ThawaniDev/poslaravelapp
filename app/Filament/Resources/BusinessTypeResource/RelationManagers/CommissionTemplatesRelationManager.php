<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeCommissionTemplates';

    protected static ?string $title = 'Commission Templates';

    protected static ?string $icon = 'heroicon-o-currency-dollar';

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
            Forms\Components\TextInput::make('commission_type')
                ->required()
                ->maxLength(50),
            Forms\Components\TextInput::make('value')
                ->numeric()
                ->step(0.01)
                ->required(),
            Forms\Components\TextInput::make('applies_to')
                ->maxLength(50),
            Forms\Components\KeyValue::make('tier_thresholds')
                ->label('Tier Thresholds')
                ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('commission_type')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('value'),
                Tables\Columns\TextColumn::make('applies_to'),
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
