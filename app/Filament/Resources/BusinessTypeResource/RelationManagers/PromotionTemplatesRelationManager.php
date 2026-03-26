<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypePromotionTemplates';

    protected static ?string $title = 'Promotion Templates';

    protected static ?string $icon = 'heroicon-o-gift';

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
            Forms\Components\TextInput::make('promotion_type')
                ->required()
                ->maxLength(50),
            Forms\Components\TextInput::make('discount_value')
                ->numeric()
                ->step(0.01),
            Forms\Components\TextInput::make('applies_to')
                ->maxLength(50),
            Forms\Components\TimePicker::make('time_start')->seconds(false),
            Forms\Components\TimePicker::make('time_end')->seconds(false),
            Forms\Components\CheckboxList::make('active_days')
                ->options([
                    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday',
                    3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
                ])
                ->columns(4)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('minimum_order')
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
                Tables\Columns\TextColumn::make('promotion_type')->badge()->color('info'),
                Tables\Columns\TextColumn::make('discount_value'),
                Tables\Columns\TextColumn::make('applies_to'),
                Tables\Columns\TextColumn::make('minimum_order'),
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
