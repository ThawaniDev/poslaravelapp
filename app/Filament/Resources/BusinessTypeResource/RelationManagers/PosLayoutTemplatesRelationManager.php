<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PosLayoutTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'posLayoutTemplates';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('POS Layout Templates');
    }

    protected static ?string $icon = 'heroicon-o-squares-2x2';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('ui.name_en'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Forms\Set $set, ?string $state, Forms\Get $get) {
                    $bt = $this->getOwnerRecord()->slug ?? '';
                    $set('layout_key', $bt . '_' . Str::slug($state ?? '', '_'));
                }),
            Forms\Components\TextInput::make('name_ar')
                ->label(__('ui.name_ar'))
                ->maxLength(255),
            Forms\Components\TextInput::make('layout_key')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(100),
            Forms\Components\Textarea::make('description')
                ->label(__('ui.description'))
                ->rows(2)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('preview_image_url')
                ->label(__('ui.preview'))
                ->url()
                ->maxLength(500),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('ui.sort_order'))
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_default')
                ->label(__('ui.is_default'))
                ->default(false),
            Forms\Components\Toggle::make('is_active')
                ->label(__('ui.is_active'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PosLayoutTemplate $record) => $record->name_ar),
                Tables\Columns\TextColumn::make('layout_key')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label(__('ui.is_default')),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('ui.is_active')),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ui.sort_order'))
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
