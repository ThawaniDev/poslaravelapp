<?php

namespace App\Filament\Resources;

use App\Domain\Website\Enums\ContactSubmissionStatus;
use App\Domain\Website\Models\WebsiteContactSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class WebsiteContactSubmissionResource extends Resource
{
    protected static ?string $model = WebsiteContactSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_website');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.contact_submissions');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_number';

    // ═══════════════════════════════════════════════════════════
    //  FORM
    // ═══════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Submission Details'))
                ->icon('heroicon-o-inbox')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reference_number')
                        ->label(__('Reference #'))
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options(collect(ContactSubmissionStatus::cases())->mapWithKeys(
                            fn ($s) => [$s->value => $s->label()]
                        ))
                        ->required(),

                    Forms\Components\TextInput::make('full_name')
                        ->label(__('Full Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('store_name')
                        ->label(__('Store Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->disabled(),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('Phone'))
                        ->disabled(),

                    Forms\Components\TextInput::make('store_type')
                        ->label(__('Store Type'))
                        ->disabled(),

                    Forms\Components\TextInput::make('branches')
                        ->label(__('Branches'))
                        ->disabled(),

                    Forms\Components\TextInput::make('source_page')
                        ->label(__('Source Page'))
                        ->disabled(),

                    Forms\Components\TextInput::make('selected_plan')
                        ->label(__('Selected Plan'))
                        ->disabled(),

                    Forms\Components\TextInput::make('inquiry_type')
                        ->label(__('Inquiry Type'))
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('contacted_at')
                        ->label(__('Contacted At')),

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

    // ═══════════════════════════════════════════════════════════
    //  TABLE
    // ═══════════════════════════════════════════════════════════

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

                Tables\Columns\TextColumn::make('store_name')
                    ->label(__('Store'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('inquiry_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'demo' => 'info',
                        'trial' => 'success',
                        'zatca' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('source_page')
                    ->label(__('Source'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('selected_plan')
                    ->label(__('Plan'))
                    ->badge()
                    ->color('primary')
                    ->toggleable()
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ContactSubmissionStatus $state) => $state->label())
                    ->color(fn (ContactSubmissionStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ContactSubmissionStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),

                Tables\Filters\SelectFilter::make('inquiry_type')
                    ->options([
                        'demo' => __('Demo Request'),
                        'general' => __('General'),
                        'zatca' => __('ZATCA'),
                        'trial' => __('Free Trial'),
                    ]),

                Tables\Filters\SelectFilter::make('source_page')
                    ->options([
                        'contact' => __('Contact Page'),
                        'home' => __('Home Page'),
                        'pricing' => __('Pricing Page'),
                        'features' => __('Features Page'),
                        'about' => __('About Page'),
                        'zatca' => __('ZATCA Page'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_contacted')
                    ->label(__('Mark Contacted'))
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WebsiteContactSubmission $record) => $record->status === ContactSubmissionStatus::New)
                    ->action(fn (WebsiteContactSubmission $record) => $record->update([
                        'status' => 'contacted',
                        'contacted_at' => now(),
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_contacted')
                        ->label(__('Mark as Contacted'))
                        ->icon('heroicon-o-phone')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update([
                            'status' => 'contacted',
                            'contacted_at' => now(),
                        ]))),
                    Tables\Actions\BulkAction::make('mark_closed')
                        ->label(__('Close'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['status' => 'closed']))),
                ]),
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  INFOLIST
    // ═══════════════════════════════════════════════════════════

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Contact Information'))
                ->icon('heroicon-o-user')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('reference_number')->label(__('Reference #'))->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('full_name')->label(__('Full Name')),
                    Infolists\Components\TextEntry::make('store_name')->label(__('Store Name')),
                    Infolists\Components\TextEntry::make('email')->label(__('Email'))->copyable(),
                    Infolists\Components\TextEntry::make('phone')->label(__('Phone'))->copyable(),
                    Infolists\Components\TextEntry::make('store_type')->label(__('Store Type')),
                    Infolists\Components\TextEntry::make('branches')->label(__('Branches')),
                    Infolists\Components\TextEntry::make('inquiry_type')->label(__('Inquiry Type'))->badge(),
                    Infolists\Components\TextEntry::make('source_page')->label(__('Source Page'))->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('selected_plan')->label(__('Selected Plan'))->badge()->color('primary'),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (ContactSubmissionStatus $state) => $state->label())
                        ->color(fn (ContactSubmissionStatus $state) => $state->color()),
                    Infolists\Components\TextEntry::make('created_at')->label(__('Submitted'))->dateTime('M j, Y H:i'),
                ]),

            Infolists\Components\Section::make(__('Message'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\TextEntry::make('message')->label('')->prose()->markdown(),
                ])
                ->visible(fn (WebsiteContactSubmission $record) => filled($record->message)),

            Infolists\Components\Section::make(__('Admin'))
                ->icon('heroicon-o-cog-6-tooth')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('admin_notes')->label(__('Notes')),
                    Infolists\Components\TextEntry::make('contacted_at')->label(__('Contacted At'))->dateTime(),
                    Infolists\Components\TextEntry::make('ip_address')->label(__('IP Address')),
                    Infolists\Components\TextEntry::make('user_agent')->label(__('User Agent'))->limit(80),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\WebsiteContactSubmissionResource\Pages\ListWebsiteContactSubmissions::route('/'),
            'view' => \App\Filament\Resources\WebsiteContactSubmissionResource\Pages\ViewWebsiteContactSubmission::route('/{record}'),
            'edit' => \App\Filament\Resources\WebsiteContactSubmissionResource\Pages\EditWebsiteContactSubmission::route('/{record}/edit'),
        ];
    }
}
