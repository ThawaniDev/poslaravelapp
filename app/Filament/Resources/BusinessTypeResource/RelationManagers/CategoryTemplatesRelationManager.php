<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeCategoryTemplates';

    protected static ?string $title = 'Category Templates';

    protected static ?string $icon = 'heroicon-o-tag';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('category_name')
                ->label('Category Name (EN)')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('category_name_ar')
                ->label('Category Name (AR)')
                ->maxLength(255),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Name (EN)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category_name_ar')
                    ->label('Name (AR)'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),
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
