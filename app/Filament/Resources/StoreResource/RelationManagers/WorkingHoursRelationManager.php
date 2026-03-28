<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use App\Domain\Core\Models\StoreWorkingHour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables;
use Filament\Tables\Table;

class WorkingHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'workingHours';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Working Hours');
    }

    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('day_of_week')
                ->options(StoreWorkingHour::DAYS)
                ->required()
                ->native(false),
            Forms\Components\Toggle::make('is_open')
                ->label(__('Open'))
                ->default(true)
                ->live(),
            Forms\Components\TimePicker::make('open_time')
                ->seconds(false)
                ->required()
                ->visible(fn (Forms\Get $get) => $get('is_open')),
            Forms\Components\TimePicker::make('close_time')
                ->seconds(false)
                ->required()
                ->visible(fn (Forms\Get $get) => $get('is_open')),
            Forms\Components\TimePicker::make('break_start')
                ->seconds(false)
                ->visible(fn (Forms\Get $get) => $get('is_open')),
            Forms\Components\TimePicker::make('break_end')
                ->seconds(false)
                ->visible(fn (Forms\Get $get) => $get('is_open')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label(__('Day'))
                    ->formatStateUsing(fn ($state) => StoreWorkingHour::DAYS[$state] ?? 'Unknown')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_open')
                    ->boolean()
                    ->label(__('Open')),
                Tables\Columns\TextColumn::make('open_time')
                    ->label(__('Opens'))
                    ->time('H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('close_time')
                    ->label(__('Closes'))
                    ->time('H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('break_start')
                    ->label(__('Break Start'))
                    ->time('H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('break_end')
                    ->label(__('Break End'))
                    ->time('H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('day_of_week')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
