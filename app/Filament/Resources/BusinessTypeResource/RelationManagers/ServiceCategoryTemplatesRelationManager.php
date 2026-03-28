<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceCategoryTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeServiceCategoryTemplates';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Service Category Templates');
    }

    protected static ?string $icon = 'heroicon-o-wrench-screwdriver';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Name (EN)'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('name_ar')
                ->label(__('Name (AR)'))
                ->maxLength(255),
            Forms\Components\TextInput::make('default_duration_minutes')
                ->label(__('Default Duration (min)'))
                ->numeric()
                ->default(60),
            Forms\Components\TextInput::make('default_price')
                ->numeric()
                ->step(0.01)
                ->default(0),
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
                Tables\Columns\TextColumn::make('default_duration_minutes')->label(__('Duration (min)')),
                Tables\Columns\TextColumn::make('default_price')->money('SAR'),
                Tables\Columns\TextColumn::make('sort_order')->label(__('Sort'))->sortable(),
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
