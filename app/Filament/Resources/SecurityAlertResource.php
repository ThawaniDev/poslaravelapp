<?php

namespace App\Filament\Resources;

use App\Domain\Security\Models\SecurityAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityAlertResource extends Resource
{
    protected static ?string $model = SecurityAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Security Alerts';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.manage_alerts']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Security Alerts')->schema([
                Forms\Components\Select::make('status')->options(array ('new' => 'New','investigating' => 'Investigating','resolved' => 'Resolved','dismissed' => 'Dismissed',)),
                Forms\Components\Textarea::make('resolution_notes')->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('alert_type')->badge(),
                Tables\Columns\TextColumn::make('severity')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\TextColumn::make('ip_address'),
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
            'index' => SecurityAlertResource\Pages\ListSecurityAlerts::route('/'),
            'edit' => SecurityAlertResource\Pages\EditSecurityAlert::route('/{record}/edit'),
        ];
    }
}
