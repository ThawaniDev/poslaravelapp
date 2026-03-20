<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\PricingPageContent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PricingPageContentResource extends Resource
{
    protected static ?string $model = PricingPageContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Pricing Page';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.pricing']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pricing Page')->schema([
                Forms\Components\TextInput::make('section_key')->required()->maxLength(255),
                Forms\Components\TextInput::make('title')->maxLength(255),
                Forms\Components\TextInput::make('title_ar')->label('Title (Arabic)')->maxLength(255),
                Forms\Components\RichEditor::make('body'),
                Forms\Components\RichEditor::make('body_ar')->label('Body (Arabic)'),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('section_key')->searchable(),
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => PricingPageContentResource\Pages\ListPricingPageContents::route('/'),
            'create' => PricingPageContentResource\Pages\CreatePricingPageContent::route('/create'),
            'edit' => PricingPageContentResource\Pages\EditPricingPageContent::route('/{record}/edit'),
        ];
    }
}
