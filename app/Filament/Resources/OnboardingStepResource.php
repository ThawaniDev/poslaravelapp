<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\OnboardingStep;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OnboardingStepResource extends Resource
{
    protected static ?string $model = OnboardingStep::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Onboarding Steps';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Onboarding Steps')->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\TextInput::make('title_ar')->label('Title (Arabic)')->maxLength(255),
                Forms\Components\RichEditor::make('description'),
                Forms\Components\TextInput::make('step_number')->required()->numeric(),
                Forms\Components\Toggle::make('is_required')->default(true),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('step_number')->sortable(),
                Tables\Columns\IconColumn::make('is_required')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            'index' => OnboardingStepResource\Pages\ListOnboardingSteps::route('/'),
            'create' => OnboardingStepResource\Pages\CreateOnboardingStep::route('/create'),
            'edit' => OnboardingStepResource\Pages\EditOnboardingStep::route('/{record}/edit'),
        ];
    }
}
