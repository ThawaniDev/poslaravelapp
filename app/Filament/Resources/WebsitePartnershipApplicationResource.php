<?php

namespace App\Filament\Resources;

use App\Domain\Website\Enums\PartnershipApplicationStatus;
use App\Domain\Website\Models\WebsitePartnershipApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebsitePartnershipApplicationResource extends Resource
{
    protected static ?string $model = WebsitePartnershipApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_website');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.partnership_applications');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Application Details'))
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reference_number')
                        ->label(__('Reference #'))
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options(collect(PartnershipApplicationStatus::cases())->mapWithKeys(
                            fn ($s) => [$s->value => $s->label()]
                        ))
                        ->required(),

                    Forms\Components\TextInput::make('company_name')
                        ->label(__('Company Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('contact_name')
                        ->label(__('Contact Name'))
                        ->disabled(),

                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->disabled(),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('Phone'))
                        ->disabled(),

                    Forms\Components\TextInput::make('partnership_type')
                        ->label(__('Partnership Type'))
                        ->disabled(),

                    Forms\Components\TextInput::make('website')
                        ->label(__('Website'))
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

                Tables\Columns\TextColumn::make('company_name')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label(__('Contact'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('partnership_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state) => match ($state) {
                        'delivery_platform' => 'success',
                        'payment_provider' => 'info',
                        'developer' => 'warning',
                        'reseller' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PartnershipApplicationStatus $state) => $state->label())
                    ->color(fn (PartnershipApplicationStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PartnershipApplicationStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),

                Tables\Filters\SelectFilter::make('partnership_type')
                    ->options([
                        'delivery_platform' => __('Delivery Platform'),
                        'payment_provider' => __('Payment Provider'),
                        'developer' => __('Developer'),
                        'reseller' => __('Reseller'),
                        'technology' => __('Technology'),
                        'other' => __('Other'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WebsitePartnershipApplication $record) => !in_array($record->status, [
                        PartnershipApplicationStatus::Approved,
                        PartnershipApplicationStatus::Rejected,
                    ]))
                    ->action(fn (WebsitePartnershipApplication $record) => $record->update(['status' => 'approved'])),
                Tables\Actions\Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WebsitePartnershipApplication $record) => !in_array($record->status, [
                        PartnershipApplicationStatus::Approved,
                        PartnershipApplicationStatus::Rejected,
                    ]))
                    ->action(fn (WebsitePartnershipApplication $record) => $record->update(['status' => 'rejected'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_all')
                        ->label(__('Approve Selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['status' => 'approved']))),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Partner Information'))
                ->icon('heroicon-o-building-office-2')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('reference_number')->label(__('Reference #'))->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('company_name')->label(__('Company')),
                    Infolists\Components\TextEntry::make('contact_name')->label(__('Contact')),
                    Infolists\Components\TextEntry::make('email')->label(__('Email'))->copyable(),
                    Infolists\Components\TextEntry::make('phone')->label(__('Phone'))->copyable(),
                    Infolists\Components\TextEntry::make('partnership_type')->label(__('Type'))->badge(),
                    Infolists\Components\TextEntry::make('website')->label(__('Website'))->url(fn ($state) => $state),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (PartnershipApplicationStatus $state) => $state->label())
                        ->color(fn (PartnershipApplicationStatus $state) => $state->color()),
                    Infolists\Components\TextEntry::make('created_at')->label(__('Submitted'))->dateTime('M j, Y H:i'),
                ]),

            Infolists\Components\Section::make(__('Message'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\TextEntry::make('message')->label('')->prose()->markdown(),
                ])
                ->visible(fn (WebsitePartnershipApplication $record) => filled($record->message)),

            Infolists\Components\Section::make(__('Admin'))
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Infolists\Components\TextEntry::make('admin_notes')->label(__('Notes')),
                    Infolists\Components\TextEntry::make('ip_address')->label(__('IP Address')),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\WebsitePartnershipApplicationResource\Pages\ListWebsitePartnershipApplications::route('/'),
            'view' => \App\Filament\Resources\WebsitePartnershipApplicationResource\Pages\ViewWebsitePartnershipApplication::route('/{record}'),
            'edit' => \App\Filament\Resources\WebsitePartnershipApplicationResource\Pages\EditWebsitePartnershipApplication::route('/{record}/edit'),
        ];
    }
}
