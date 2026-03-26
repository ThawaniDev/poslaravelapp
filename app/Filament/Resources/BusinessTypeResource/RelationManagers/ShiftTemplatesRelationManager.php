<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeShiftTemplates';

    protected static ?string $title = 'Shift Templates';

    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Shift Name (EN)')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('name_ar')
                ->label('Shift Name (AR)')
                ->maxLength(255),
            Forms\Components\TimePicker::make('start_time')
                ->required()
                ->seconds(false),
            Forms\Components\TimePicker::make('end_time')
                ->required()
                ->seconds(false),
            Forms\Components\CheckboxList::make('days_of_week')
                ->options([
                    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday',
                    3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
                ])
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('break_duration_minutes')
                ->label('Break (min)')
                ->numeric()
                ->default(30),
            Forms\Components\Toggle::make('is_default')
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
                Tables\Columns\TextColumn::make('start_time')->time('H:i'),
                Tables\Columns\TextColumn::make('end_time')->time('H:i'),
                Tables\Columns\TextColumn::make('break_duration_minutes')->label('Break (min)'),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
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
