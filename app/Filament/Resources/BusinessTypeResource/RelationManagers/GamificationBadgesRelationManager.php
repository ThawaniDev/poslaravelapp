<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables;
use Filament\Tables\Table;

class GamificationBadgesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeGamificationBadges';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Gamification Badges');
    }

    protected static ?string $icon = 'heroicon-o-trophy';

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
            Forms\Components\Textarea::make('description')
                ->rows(2),
            Forms\Components\Textarea::make('description_ar')
                ->label(__('Description (AR)'))
                ->rows(2),
            Forms\Components\TextInput::make('icon_url')
                ->label(__('Icon URL'))
                ->url()
                ->maxLength(500),
            Forms\Components\TextInput::make('trigger_type')
                ->required()
                ->maxLength(50),
            Forms\Components\TextInput::make('trigger_threshold')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('points_reward')
                ->numeric()
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
                Tables\Columns\TextColumn::make('trigger_type')->badge()->color('info'),
                Tables\Columns\TextColumn::make('trigger_threshold'),
                Tables\Columns\TextColumn::make('points_reward')->badge()->color('success'),
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
