<?php

namespace App\Filament\Resources;

use App\Domain\Website\Enums\ConsultationRequestStatus;
use App\Domain\Website\Models\WebsiteConsultationRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebsiteConsultationRequestResource extends Resource
{
    protected static ?string $model = WebsiteConsultationRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_website');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.consultation_requests');
    }

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Consultation Details'))
                ->icon('heroicon-o-clipboard-document-check')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reference_number')
                        ->label(__('Reference #'))
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options(collect(ConsultationRequestStatus::cases())->mapWithKeys(
                            fn ($s) => [$s->value => $s->label()]
                        ))
                        ->required(),

                    Forms\Components\TextInput::make('full_name')
                        ->label(__('Full Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('business_name')
                        ->label(__('Business Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->disabled(),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('Phone'))
                        ->disabled(),

                    Forms\Components\TextInput::make('consultation_type')
                        ->label(__('Consultation Type'))
                        ->disabled(),

                    Forms\Components\TextInput::make('branches')
                        ->label(__('Branches'))
                        ->disabled(),

                    Forms\Components\TextInput::make('cr_number')
                        ->label(__('CR Number'))
                        ->disabled(),

                    Forms\Components\TextInput::make('vat_number')
                        ->label(__('VAT Number'))
                        ->disabled(),

                    Forms\Components\TextInput::make('current_pos_system')
                        ->label(__('Current POS'))
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label(__('Scheduled At')),

                    Forms\Components\Textarea::make('message')
                        ->label(__('Message'))
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('admin_notes')
                        ->label(__('Admin Notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('Ref #'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('business_name')
                    ->label(__('Business'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('consultation_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : '—')
                    ->color(fn (?string $state) => match ($state) {
                        'zatca_phase2' => 'danger',
                        'compliance_audit' => 'warning',
                        'migration' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('branches')
                    ->label(__('Branches'))
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ConsultationRequestStatus $state) => $state->label())
                    ->color(fn (ConsultationRequestStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('Scheduled'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder(__('Not scheduled')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ConsultationRequestStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),

                Tables\Filters\SelectFilter::make('consultation_type')
                    ->options([
                        'zatca_phase2' => __('ZATCA Phase 2'),
                        'compliance_audit' => __('Compliance Audit'),
                        'migration' => __('Migration'),
                        'general' => __('General'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('schedule')
                    ->label(__('Schedule'))
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label(__('Schedule Date & Time'))
                            ->required()
                            ->minDate(now()),
                    ])
                    ->action(fn (WebsiteConsultationRequest $record, array $data) => $record->update([
                        'scheduled_at' => $data['scheduled_at'],
                        'status' => 'scheduled',
                    ]))
                    ->visible(fn (WebsiteConsultationRequest $record) => in_array($record->status->value, ['new', 'scheduled'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label(__('Mark as Completed'))
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['status' => 'completed']))),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Contact Information'))
                ->icon('heroicon-o-user')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('reference_number')->label(__('Reference #'))->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('full_name')->label(__('Full Name')),
                    Infolists\Components\TextEntry::make('business_name')->label(__('Business')),
                    Infolists\Components\TextEntry::make('email')->label(__('Email'))->copyable(),
                    Infolists\Components\TextEntry::make('phone')->label(__('Phone'))->copyable(),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (ConsultationRequestStatus $state) => $state->label())
                        ->color(fn (ConsultationRequestStatus $state) => $state->color()),
                ]),

            Infolists\Components\Section::make(__('Consultation Details'))
                ->icon('heroicon-o-clipboard-document-check')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('consultation_type')
                        ->label(__('Type'))
                        ->badge()
                        ->color(fn (?string $state) => match ($state) {
                            'zatca_phase2' => 'danger',
                            'compliance_audit' => 'warning',
                            'migration' => 'info',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('branches')->label(__('Branches')),
                    Infolists\Components\TextEntry::make('cr_number')->label(__('CR Number')),
                    Infolists\Components\TextEntry::make('vat_number')->label(__('VAT Number')),
                    Infolists\Components\TextEntry::make('current_pos_system')->label(__('Current POS')),
                    Infolists\Components\TextEntry::make('scheduled_at')->label(__('Scheduled At'))->dateTime('M j, Y H:i'),
                ]),

            Infolists\Components\Section::make(__('Message'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\TextEntry::make('message')->label('')->prose(),
                ])
                ->visible(fn (WebsiteConsultationRequest $record) => filled($record->message)),

            Infolists\Components\Section::make(__('Admin'))
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Infolists\Components\TextEntry::make('admin_notes')->label(__('Notes')),
                    Infolists\Components\TextEntry::make('ip_address')->label(__('IP Address')),
                    Infolists\Components\TextEntry::make('created_at')->label(__('Submitted'))->dateTime('M j, Y H:i'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\WebsiteConsultationRequestResource\Pages\ListWebsiteConsultationRequests::route('/'),
            'view' => \App\Filament\Resources\WebsiteConsultationRequestResource\Pages\ViewWebsiteConsultationRequest::route('/{record}'),
            'edit' => \App\Filament\Resources\WebsiteConsultationRequestResource\Pages\EditWebsiteConsultationRequest::route('/{record}/edit'),
        ];
    }
}
