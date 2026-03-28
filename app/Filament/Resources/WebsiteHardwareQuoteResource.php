<?php

namespace App\Filament\Resources;

use App\Domain\Website\Enums\HardwareQuoteStatus;
use App\Domain\Website\Models\WebsiteHardwareQuote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebsiteHardwareQuoteResource extends Resource
{
    protected static ?string $model = WebsiteHardwareQuote::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_website');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.hardware_quotes');
    }

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Quote Details'))
                ->icon('heroicon-o-computer-desktop')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reference_number')
                        ->label(__('Reference #'))
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options(collect(HardwareQuoteStatus::cases())->mapWithKeys(
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

                    Forms\Components\TextInput::make('hardware_bundle')
                        ->label(__('Bundle'))
                        ->disabled(),

                    Forms\Components\TextInput::make('terminal_quantity')
                        ->label(__('Terminal Qty'))
                        ->disabled(),

                    Forms\Components\Toggle::make('needs_printer')
                        ->label(__('Printer'))
                        ->disabled(),

                    Forms\Components\Toggle::make('needs_scanner')
                        ->label(__('Scanner'))
                        ->disabled(),

                    Forms\Components\Toggle::make('needs_cash_drawer')
                        ->label(__('Cash Drawer'))
                        ->disabled(),

                    Forms\Components\Toggle::make('needs_payment_terminal')
                        ->label(__('Payment Terminal'))
                        ->disabled(),

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
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hardware_bundle')
                    ->label(__('Bundle'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : '—')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('terminal_quantity')
                    ->label(__('Qty'))
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('needs_printer')
                    ->label(__('🖨️'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('needs_scanner')
                    ->label('📷')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (HardwareQuoteStatus $state) => $state->label())
                    ->color(fn (HardwareQuoteStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(HardwareQuoteStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),

                Tables\Filters\SelectFilter::make('hardware_bundle')
                    ->options([
                        'starter_kit' => __('Starter Kit'),
                        'pro_bundle' => __('Pro Bundle'),
                        'enterprise_suite' => __('Enterprise Suite'),
                        'custom' => __('Custom'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_quoted')
                        ->label(__('Mark as Quoted'))
                        ->icon('heroicon-o-document-check')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['status' => 'quoted']))),
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
                        ->formatStateUsing(fn (HardwareQuoteStatus $state) => $state->label())
                        ->color(fn (HardwareQuoteStatus $state) => $state->color()),
                ]),

            Infolists\Components\Section::make(__('Hardware Requirements'))
                ->icon('heroicon-o-computer-desktop')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('hardware_bundle')->label(__('Bundle'))->badge()->color('primary'),
                    Infolists\Components\TextEntry::make('terminal_quantity')->label(__('Terminals')),
                    Infolists\Components\IconEntry::make('needs_printer')->label(__('Printer'))->boolean(),
                    Infolists\Components\IconEntry::make('needs_scanner')->label(__('Scanner'))->boolean(),
                    Infolists\Components\IconEntry::make('needs_cash_drawer')->label(__('Cash Drawer'))->boolean(),
                    Infolists\Components\IconEntry::make('needs_payment_terminal')->label(__('Payment Terminal'))->boolean(),
                ]),

            Infolists\Components\Section::make(__('Message'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\TextEntry::make('message')->label('')->prose(),
                ])
                ->visible(fn (WebsiteHardwareQuote $record) => filled($record->message)),

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
            'index' => \App\Filament\Resources\WebsiteHardwareQuoteResource\Pages\ListWebsiteHardwareQuotes::route('/'),
            'view' => \App\Filament\Resources\WebsiteHardwareQuoteResource\Pages\ViewWebsiteHardwareQuote::route('/{record}'),
            'edit' => \App\Filament\Resources\WebsiteHardwareQuoteResource\Pages\EditWebsiteHardwareQuote::route('/{record}/edit'),
        ];
    }
}
