<?php

namespace App\Filament\Resources;

use App\Domain\Announcement\Models\PlatformAnnouncement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformAnnouncementResource extends Resource
{
    protected static ?string $model = PlatformAnnouncement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $navigationLabel = 'Announcements';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['announcements.view', 'announcements.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Announcements')->schema([
                Forms\Components\Select::make('type')->options(array ('info' => 'Info','warning' => 'Warning','maintenance' => 'Maintenance','promotion' => 'Promotion',))->required(),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\TextInput::make('title_ar')->label('Title (Arabic)')->maxLength(255),
                Forms\Components\RichEditor::make('body')->required(),
                Forms\Components\RichEditor::make('body_ar')->label('Body (Arabic)'),
                Forms\Components\Toggle::make('is_banner'),
                Forms\Components\Toggle::make('send_push'),
                Forms\Components\Toggle::make('send_email'),
                Forms\Components\DateTimePicker::make('display_start_at'),
                Forms\Components\DateTimePicker::make('display_end_at'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\IconColumn::make('is_banner')->boolean(),
                Tables\Columns\TextColumn::make('display_start_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('display_end_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => PlatformAnnouncementResource\Pages\ListPlatformAnnouncements::route('/'),
            'create' => PlatformAnnouncementResource\Pages\CreatePlatformAnnouncement::route('/create'),
            'edit' => PlatformAnnouncementResource\Pages\EditPlatformAnnouncement::route('/{record}/edit'),
        ];
    }
}
