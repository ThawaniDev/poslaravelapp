<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Domain\Core\Enums\BusinessType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StoresRelationManager extends RelationManager
{
    protected static string $relationship = 'stores';

    protected static ?string $title = 'Stores';

    protected static ?string $icon = 'heroicon-o-building-storefront';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Store Name (EN)')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('name_ar')
                ->label('Store Name (AR)')
                ->maxLength(255),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('business_type')
                ->options(BusinessType::class)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('phone')->tel(),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('city')->maxLength(100),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Toggle::make('is_main_branch')->default(false),
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
                Tables\Columns\TextColumn::make('branch_code')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('city')
                    ->sortable(),
                Tables\Columns\TextColumn::make('business_type')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'retail' => 'info', 'restaurant' => 'warning',
                        'pharmacy', 'grocery' => 'success', default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\IconColumn::make('is_main_branch')
                    ->boolean()
                    ->label('Main'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
