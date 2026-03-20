<?php

namespace App\Filament\Resources;

use App\Domain\Notification\Models\NotificationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Notifications';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['notifications.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Templates')->schema([
                Forms\Components\TextInput::make('event_key')->required()->maxLength(255),
                Forms\Components\Select::make('channel')->options(array ('push' => 'Push','sms' => 'SMS','email' => 'Email','whatsapp' => 'WhatsApp',))->required(),
                Forms\Components\TextInput::make('title_en')->label('Title (English)')->maxLength(255),
                Forms\Components\TextInput::make('title_ar')->label('Title (Arabic)')->maxLength(255),
                Forms\Components\Textarea::make('body_en')->label('Body (English)')->rows(3),
                Forms\Components\Textarea::make('body_ar')->label('Body (Arabic)')->rows(3),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_key')->searchable(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('title_en')->label('Title (EN)')->limit(40),
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
            'index' => NotificationTemplateResource\Pages\ListNotificationTemplates::route('/'),
            'create' => NotificationTemplateResource\Pages\CreateNotificationTemplate::route('/create'),
            'edit' => NotificationTemplateResource\Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
