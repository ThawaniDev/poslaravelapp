<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Services\SupportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'ticket_number';

    public static function getNavigationLabel(): string
    {
        return __('support.nav_tickets');
    }

    public static function getModelLabel(): string
    {
        return __('support.ticket');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support.tickets');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) SupportTicket::unresolved()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = SupportTicket::unresolved()->count();

        return $count > 10 ? 'danger' : ($count > 0 ? 'warning' : 'success');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['tickets.view', 'tickets.respond']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ticket_number', 'subject', 'description'];
    }

    // ═══════════════════════════════════════════════════════════
    //  FORM
    // ═══════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('support.ticket_details'))
                ->description(__('support.ticket_details_desc'))
                ->icon('heroicon-o-ticket')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label(__('support.organization'))
                        ->relationship('organization', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('store_id', null)),

                    Forms\Components\Select::make('store_id')
                        ->label(__('support.store'))
                        ->options(function (Forms\Get $get) {
                            $orgId = $get('organization_id');
                            if (!$orgId) {
                                return [];
                            }

                            return Store::where('organization_id', $orgId)->pluck('name', 'id');
                        })
                        ->searchable()
                        ->nullable(),

                    Forms\Components\TextInput::make('subject')
                        ->label(__('support.subject'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('description')
                        ->label(__('support.description'))
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('category')
                        ->label(__('support.category'))
                        ->options(TicketCategory::class)
                        ->required(),

                    Forms\Components\Select::make('priority')
                        ->label(__('support.priority'))
                        ->options(TicketPriority::class)
                        ->default('medium')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label(__('support.status'))
                        ->options(TicketStatus::class)
                        ->default('open')
                        ->required()
                        ->visibleOn('edit'),

                    Forms\Components\Select::make('assigned_to')
                        ->label(__('support.assigned_to'))
                        ->options(AdminUser::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
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
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label(__('support.ticket_number'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('subject')
                    ->label(__('support.subject'))
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (SupportTicket $record) => $record->subject),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label(__('support.organization'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('support.category'))
                    ->badge()
                    ->formatStateUsing(fn (TicketCategory $state) => $state->label())
                    ->color(fn (TicketCategory $state) => $state->color())
                    ->icon(fn (TicketCategory $state) => $state->icon()),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('support.priority'))
                    ->badge()
                    ->formatStateUsing(fn (TicketPriority $state) => $state->label())
                    ->color(fn (TicketPriority $state) => $state->color())
                    ->icon(fn (TicketPriority $state) => $state->icon()),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('support.status'))
                    ->badge()
                    ->formatStateUsing(fn (TicketStatus $state) => $state->label())
                    ->color(fn (TicketStatus $state) => $state->color())
                    ->icon(fn (TicketStatus $state) => $state->icon()),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label(__('support.assigned_to'))
                    ->placeholder(__('support.unassigned'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sla_deadline_at')
                    ->label(__('support.sla_deadline'))
                    ->dateTime()
                    ->color(fn (SupportTicket $record) => match ($record->sla_badge) {
                        'breached' => 'danger',
                        'on_track' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('messages_count')
                    ->label(__('support.messages'))
                    ->counts('messages')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('support.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('support.status'))
                    ->options(TicketStatus::class),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('support.priority'))
                    ->options(TicketPriority::class),

                Tables\Filters\SelectFilter::make('category')
                    ->label(__('support.category'))
                    ->options(TicketCategory::class),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label(__('support.assigned_to'))
                    ->options(AdminUser::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('sla_breached')
                    ->label(__('support.sla_breached'))
                    ->queries(
                        true: fn ($query) => $query->slaBreach(),
                        false: fn ($query) => $query->where(
                            fn ($q) => $q->whereNull('sla_deadline_at')
                                ->orWhere('sla_deadline_at', '>=', now())
                                ->orWhereIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
                        ),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('assign')
                    ->label(__('support.assign'))
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('assigned_to')
                            ->label(__('support.assign_to_agent'))
                            ->options(AdminUser::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SupportTicket $record, array $data) {
                        $admin = auth('admin')->user();
                        app(SupportService::class)->assignTicket($record, $data['assigned_to'], $admin->id);
                        Notification::make()->success()->title(__('support.ticket_assigned'))->send();
                    })
                    ->visible(fn () => auth('admin')->user()?->hasAnyPermission(['tickets.assign'])),
                Tables\Actions\Action::make('resolve')
                    ->label(__('support.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SupportTicket $record) {
                        $admin = auth('admin')->user();
                        app(SupportService::class)->changeStatus($record, TicketStatus::Resolved, $admin->id);
                        Notification::make()->success()->title(__('support.ticket_resolved'))->send();
                    })
                    ->visible(fn (SupportTicket $record) => $record->status !== TicketStatus::Resolved && $record->status !== TicketStatus::Closed),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_assign')
                        ->label(__('support.bulk_assign'))
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('assigned_to')
                                ->label(__('support.assign_to_agent'))
                                ->options(AdminUser::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(fn ($r) => $r->update(['assigned_to' => $data['assigned_to']]));
                            Notification::make()->success()->title(__('support.tickets_assigned'))->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('bulk_close')
                        ->label(__('support.bulk_close'))
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->update(['status' => TicketStatus::Closed, 'closed_at' => now()]));
                            Notification::make()->success()->title(__('support.tickets_closed'))->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('bulk_priority')
                        ->label(__('support.bulk_change_priority'))
                        ->icon('heroicon-o-arrow-up')
                        ->form([
                            Forms\Components\Select::make('priority')
                                ->label(__('support.priority'))
                                ->options(TicketPriority::class)
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(fn ($r) => $r->update(['priority' => $data['priority']]));
                            Notification::make()->success()->title(__('support.priority_updated'))->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    // ═══════════════════════════════════════════════════════════
    //  INFOLIST (View Page)
    // ═══════════════════════════════════════════════════════════

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Group::make([
                Infolists\Components\Section::make(__('support.ticket_info'))
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Infolists\Components\TextEntry::make('ticket_number')
                            ->label(__('support.ticket_number'))
                            ->weight('bold')
                            ->size('lg')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('subject')
                            ->label(__('support.subject'))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('category')
                            ->label(__('support.category'))
                            ->badge()
                            ->formatStateUsing(fn (TicketCategory $state) => $state->label())
                            ->color(fn (TicketCategory $state) => $state->color()),

                        Infolists\Components\TextEntry::make('priority')
                            ->label(__('support.priority'))
                            ->badge()
                            ->formatStateUsing(fn (TicketPriority $state) => $state->label())
                            ->color(fn (TicketPriority $state) => $state->color()),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('support.status'))
                            ->badge()
                            ->formatStateUsing(fn (TicketStatus $state) => $state->label())
                            ->color(fn (TicketStatus $state) => $state->color()),

                        Infolists\Components\TextEntry::make('assignedTo.name')
                            ->label(__('support.assigned_to'))
                            ->placeholder(__('support.unassigned')),
                    ])->columns(2),

                Infolists\Components\Section::make(__('support.description'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make(__('support.conversation'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('messages')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('sender_name')
                                    ->label('')
                                    ->weight('bold')
                                    ->color(fn (SupportTicketMessage $record) => $record->isAdminMessage() ? 'primary' : 'success'),

                                Infolists\Components\TextEntry::make('message_text')
                                    ->label('')
                                    ->html(),

                                Infolists\Components\IconEntry::make('is_internal_note')
                                    ->label(__('support.internal_note'))
                                    ->boolean()
                                    ->visible(fn (SupportTicketMessage $record) => $record->is_internal_note),

                                Infolists\Components\TextEntry::make('sent_at')
                                    ->label('')
                                    ->dateTime()
                                    ->color('gray')
                                    ->size('sm'),
                            ])
                            ->columnSpanFull(),
                    ]),
            ])->columnSpan(2),

            Infolists\Components\Group::make([
                Infolists\Components\Section::make(__('support.organization_info'))
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Infolists\Components\TextEntry::make('organization.name')
                            ->label(__('support.organization')),

                        Infolists\Components\TextEntry::make('store.name')
                            ->label(__('support.store'))
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label(__('support.submitted_by'))
                            ->placeholder('—'),
                    ]),

                Infolists\Components\Section::make(__('support.sla_tracking'))
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Infolists\Components\TextEntry::make('sla_deadline_at')
                            ->label(__('support.sla_deadline'))
                            ->dateTime()
                            ->color(fn (SupportTicket $record) => match ($record->sla_badge) {
                                'breached' => 'danger',
                                'on_track' => 'success',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('first_response_at')
                            ->label(__('support.first_response'))
                            ->dateTime()
                            ->placeholder(__('support.no_response_yet')),

                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label(__('support.resolved_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('closed_at')
                            ->label(__('support.closed_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('support.created_at'))
                            ->dateTime(),
                    ]),
            ])->columnSpan(1),
        ])->columns(3);
    }

    // ═══════════════════════════════════════════════════════════
    //  PAGES & WIDGETS
    // ═══════════════════════════════════════════════════════════

    public static function getPages(): array
    {
        return [
            'index'  => SupportTicketResource\Pages\ListSupportTickets::route('/'),
            'create' => SupportTicketResource\Pages\CreateSupportTicket::route('/create'),
            'view'   => SupportTicketResource\Pages\ViewSupportTicket::route('/{record}'),
            'edit'   => SupportTicketResource\Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            SupportTicketResource\Widgets\SupportStatsOverview::class,
            SupportTicketResource\Widgets\TicketVolumeChart::class,
            SupportTicketResource\Widgets\TicketsByCategoryChart::class,
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('messages');
    }
}
