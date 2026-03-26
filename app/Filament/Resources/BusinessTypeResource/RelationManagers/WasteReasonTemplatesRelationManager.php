<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WasteReasonTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeWasteReasonTemplates';

    protected static ?string $title = 'Waste Reason Templates';

    protected static ?string $icon = 'heroicon-o-trash';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reason_code')
                ->required()
                ->maxLength(50),
            Forms\Components\TextInput::make('name')
                ->label('Name (EN)')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('name_ar')
                ->label('Name (AR)')
                ->maxLength(255),
            Forms\Components\TextInput::make('category')
                ->maxLength(50),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('requires_approval')
                ->default(false),
            Forms\Components\Toggle::make('affects_cost_reporting')
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
                Tables\Columns\TextColumn::make('reason_code')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->name_ar),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\IconColumn::make('requires_approval')->boolean()->label('Approval'),
                Tables\Columns\IconColumn::make('affects_cost_reporting')->boolean()->label('Cost Report'),
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
