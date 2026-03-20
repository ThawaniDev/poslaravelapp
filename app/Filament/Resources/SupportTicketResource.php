<?php

namespace App\Filament\Resources;

use App\Domain\Support\Models\SupportTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['tickets.view', 'tickets.respond']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tickets')->schema([
                Forms\Components\TextInput::make('subject')->required()->maxLength(255),
                Forms\Components\RichEditor::make('description')->required(),
                Forms\Components\Select::make('category')->options(array ('technical' => 'Technical','billing' => 'Billing','general' => 'General','bug' => 'Bug Report','feature' => 'Feature Request',)),
                Forms\Components\Select::make('priority')->options(array ('low' => 'Low','medium' => 'Medium','high' => 'High','critical' => 'Critical',)),
                Forms\Components\Select::make('status')->options(array ('open' => 'Open','pending' => 'Pending','in_progress' => 'In Progress','resolved' => 'Resolved','closed' => 'Closed',)),
                Forms\Components\TextInput::make('assigned_to')->label('Assigned To')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
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
            'index' => SupportTicketResource\Pages\ListSupportTickets::route('/'),
            'create' => SupportTicketResource\Pages\CreateSupportTicket::route('/create'),
            'edit' => SupportTicketResource\Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
