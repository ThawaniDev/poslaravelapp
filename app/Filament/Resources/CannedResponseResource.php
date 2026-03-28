<?php

namespace App\Filament\Resources;

use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Models\CannedResponse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CannedResponseResource extends Resource
{
    protected static ?string $model = CannedResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_support');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('support.nav_canned_responses');
    }

    public static function getModelLabel(): string
    {
        return __('support.canned_response');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support.canned_responses');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['tickets.respond', 'kb.manage']);
    }

    // ═══════════════════════════════════════════════════════════
    //  FORM
    // ═══════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('support.response_details'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('support.title'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('shortcut')
                        ->label(__('support.shortcut'))
                        ->helperText(__('support.shortcut_help'))
                        ->prefix('/')
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('category')
                        ->label(__('support.category'))
                        ->options(TicketCategory::class),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('support.is_active'))
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make(__('support.response_body'))
                ->icon('heroicon-o-language')
                ->schema([
                    Forms\Components\RichEditor::make('body')
                        ->label(__('support.body_en'))
                        ->required(),

                    Forms\Components\RichEditor::make('body_ar')
                        ->label(__('support.body_ar'))
                        ->required(),
                ])->columns(2),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  TABLE
    // ═══════════════════════════════════════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('support.title'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('shortcut')
                    ->label(__('support.shortcut'))
                    ->prefix('/')
                    ->searchable()
                    ->placeholder('—')
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('support.category'))
                    ->badge()
                    ->formatStateUsing(fn (?TicketCategory $state) => $state?->label() ?? '—')
                    ->color(fn (?TicketCategory $state) => $state?->color() ?? 'gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('support.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('support.created_by'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('support.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('support.category'))
                    ->options(TicketCategory::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('support.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (CannedResponse $record) => $record->is_active
                        ? __('support.deactivate')
                        : __('support.activate'))
                    ->icon(fn (CannedResponse $record) => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (CannedResponse $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (CannedResponse $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        Notification::make()->success()
                            ->title($record->is_active ? __('support.activated') : __('support.deactivated'))
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('title');
    }

    // ═══════════════════════════════════════════════════════════
    //  INFOLIST
    // ═══════════════════════════════════════════════════════════

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('support.response_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('title')
                        ->label(__('support.title')),

                    Infolists\Components\TextEntry::make('shortcut')
                        ->label(__('support.shortcut'))
                        ->prefix('/')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('category')
                        ->label(__('support.category'))
                        ->badge()
                        ->formatStateUsing(fn (?TicketCategory $state) => $state?->label() ?? '—')
                        ->color(fn (?TicketCategory $state) => $state?->color() ?? 'gray'),

                    Infolists\Components\IconEntry::make('is_active')
                        ->label(__('support.is_active'))
                        ->boolean(),
                ])->columns(2),

            Infolists\Components\Section::make(__('support.response_body'))
                ->schema([
                    Infolists\Components\TextEntry::make('body')
                        ->label(__('support.body_en'))
                        ->html(),

                    Infolists\Components\TextEntry::make('body_ar')
                        ->label(__('support.body_ar'))
                        ->html(),
                ])->columns(2),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PAGES
    // ═══════════════════════════════════════════════════════════

    public static function getPages(): array
    {
        return [
            'index'  => CannedResponseResource\Pages\ListCannedResponses::route('/'),
            'create' => CannedResponseResource\Pages\CreateCannedResponse::route('/create'),
            'view'   => CannedResponseResource\Pages\ViewCannedResponse::route('/{record}'),
            'edit'   => CannedResponseResource\Pages\EditCannedResponse::route('/{record}/edit'),
        ];
    }
}
