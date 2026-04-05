<?php

namespace App\Filament\Resources\PredefinedProductResource\RelationManagers;

use App\Services\SupabaseStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Product Images');
    }

    protected static ?string $icon = 'heroicon-o-photo';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('image_url')
                ->label(__('Product Image'))
                ->image()
                ->imageEditor()
                ->maxSize(5120)
                ->required()
                ->columnSpanFull()
                ->saveUploadedFileUsing(function ($file) {
                    return app(SupabaseStorageService::class)->upload($file, 'ProductsImages');
                })
                ->deleteUploadedFileUsing(function ($file) {
                    app(SupabaseStorageService::class)->delete($file);
                }),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(__('Image'))
                    ->getStateUsing(fn ($record) => SupabaseStorageService::resolveUrl($record->image_url))
                    ->square()
                    ->size(80),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Sort'))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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