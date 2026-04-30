<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GiftRegistryTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeGiftRegistryTypes';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Default Gift Registry Types');
    }

    protected static ?string $icon = 'heroicon-o-gift';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Registry Type Name (EN)'))
                ->required()
                ->maxLength(100)
                ->placeholder(__('ui.placeholder_wedding')),
            Forms\Components\TextInput::make('name_ar')
                ->label(__('Registry Type Name (AR)'))
                ->required()
                ->maxLength(100)
                ->placeholder('زفاف'),
            Forms\Components\TextInput::make('icon')
                ->label(__('Icon / Emoji'))
                ->maxLength(10)
                ->placeholder('💍'),
            Forms\Components\TextInput::make('default_expiry_days')
                ->label(__('Default Expiry (days)'))
                ->numeric()
                ->default(90)
                ->minValue(1),
            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('allow_public_sharing')
                ->label(__('Allow Public Sharing'))
                ->default(true),
            Forms\Components\Toggle::make('allow_partial_fulfilment')
                ->label(__('Allow Partial Fulfilment'))
                ->default(true),
            Forms\Components\Toggle::make('require_minimum_items')
                ->label(__('Require Minimum Items'))
                ->default(false)
                ->live(),
            Forms\Components\TextInput::make('minimum_items_count')
                ->label(__('Minimum Items Count'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->visible(fn (Forms\Get $get) => (bool) $get('require_minimum_items')),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('Sort Order'))
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('')
                    ->width(40),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name (EN)'))
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->name_ar),
                Tables\Columns\TextColumn::make('default_expiry_days')
                    ->label(__('Expires in'))
                    ->formatStateUsing(fn ($state) => "{$state} " . __('days')),
                Tables\Columns\IconColumn::make('allow_public_sharing')
                    ->label(__('Public'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('allow_partial_fulfilment')
                    ->label(__('Partial'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Sort'))
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
