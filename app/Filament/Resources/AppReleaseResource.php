<?php

namespace App\Filament\Resources;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppReleaseResource extends Resource
{
    protected static ?string $model = AppRelease::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-circle';

    protected static ?string $navigationGroup = 'Updates';

    protected static ?string $navigationLabel = 'App Releases';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['app_updates.view', 'app_updates.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('App Releases')->schema([
                Forms\Components\TextInput::make('version_number')->required()->maxLength(255),
                Forms\Components\Select::make('platform')->options(array ('android' => 'Android','ios' => 'iOS','windows' => 'Windows','macos' => 'macOS','web' => 'Web',))->required(),
                Forms\Components\Select::make('channel')->options(array ('stable' => 'Stable','beta' => 'Beta','alpha' => 'Alpha',))->required(),
                Forms\Components\RichEditor::make('release_notes_en')->label('Notes (English)'),
                Forms\Components\RichEditor::make('release_notes_ar')->label('Notes (Arabic)'),
                Forms\Components\Toggle::make('force_update'),
                Forms\Components\TextInput::make('rollout_percentage')->numeric()->default(100),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\DateTimePicker::make('released_at'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('platform')->badge(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('force_update')->boolean(),
                Tables\Columns\TextColumn::make('rollout_percentage'),
                Tables\Columns\TextColumn::make('released_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => AppReleaseResource\Pages\ListAppReleases::route('/'),
            'create' => AppReleaseResource\Pages\CreateAppRelease::route('/create'),
            'edit' => AppReleaseResource\Pages\EditAppRelease::route('/{record}/edit'),
        ];
    }
}
