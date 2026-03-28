<?php

namespace App\Filament\Resources;

use App\Domain\Website\Enums\NewsletterStatus;
use App\Domain\Website\Models\WebsiteNewsletterSubscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebsiteNewsletterSubscriberResource extends Resource
{
    protected static ?string $model = WebsiteNewsletterSubscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_website');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.newsletter_subscribers');
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Subscriber Details'))
                ->icon('heroicon-o-envelope')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label(__('Email'))
                        ->email()
                        ->required()
                        ->disabled(),

                    Forms\Components\Select::make('status')
                        ->label(__('Status'))
                        ->options(collect(NewsletterStatus::cases())->mapWithKeys(
                            fn ($s) => [$s->value => $s->label()]
                        ))
                        ->required(),

                    Forms\Components\TextInput::make('source_page')
                        ->label(__('Source Page'))
                        ->disabled(),

                    Forms\Components\TextInput::make('ip_address')
                        ->label(__('IP Address'))
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('subscribed_at')
                        ->label(__('Subscribed At'))
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('unsubscribed_at')
                        ->label(__('Unsubscribed At'))
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (NewsletterStatus $state) => $state->label())
                    ->color(fn (NewsletterStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('source_page')
                    ->label(__('Source'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label(__('Subscribed'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unsubscribed_at')
                    ->label(__('Unsubscribed'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('subscribed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(NewsletterStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => $s->label()]
                    )),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('unsubscribe')
                        ->label(__('Unsubscribe'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update([
                            'status' => 'unsubscribed',
                            'unsubscribed_at' => now(),
                        ]))),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\WebsiteNewsletterSubscriberResource\Pages\ListWebsiteNewsletterSubscribers::route('/'),
            'edit' => \App\Filament\Resources\WebsiteNewsletterSubscriberResource\Pages\EditWebsiteNewsletterSubscriber::route('/{record}/edit'),
        ];
    }
}
