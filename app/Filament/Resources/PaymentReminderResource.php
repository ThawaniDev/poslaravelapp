<?php

namespace App\Filament\Resources;

use App\Domain\Announcement\Enums\ReminderChannel;
use App\Domain\Announcement\Enums\ReminderType;
use App\Domain\Announcement\Models\PaymentReminder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentReminderResource extends Resource
{
    protected static ?string $model = PaymentReminder::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_business');
    }

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('announcements.payment_reminders');
    }

    public static function getModelLabel(): string
    {
        return __('announcements.payment_reminder');
    }

    public static function getPluralModelLabel(): string
    {
        return __('announcements.payment_reminders');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['announcements.view', 'announcements.manage', 'subscriptions.view']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('announcements.reminder_details'))
                ->schema([
                    Forms\Components\Select::make('store_subscription_id')
                        ->label(__('announcements.subscription'))
                        ->relationship('storeSubscription', 'id')
                        ->required()
                        ->disabled(),
                    Forms\Components\Select::make('reminder_type')
                        ->label(__('announcements.reminder_type'))
                        ->options(collect(ReminderType::cases())->mapWithKeys(fn ($c) => [$c->value => __('announcements.reminder_type_' . $c->value)]))
                        ->required()
                        ->disabled()
                        ->native(false),
                    Forms\Components\Select::make('channel')
                        ->label(__('announcements.channel'))
                        ->options(collect(ReminderChannel::cases())->mapWithKeys(fn ($c) => [$c->value => __('announcements.channel_' . $c->value)]))
                        ->required()
                        ->disabled()
                        ->native(false),
                    Forms\Components\DateTimePicker::make('sent_at')
                        ->label(__('announcements.sent_at'))
                        ->disabled(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('storeSubscription.organization.name')
                    ->label(__('announcements.organization'))
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('reminder_type')
                    ->label(__('announcements.reminder_type'))
                    ->formatStateUsing(fn ($state) => __('announcements.reminder_type_' . ($state instanceof \BackedEnum ? $state->value : $state)))
                    ->badge()
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state : ReminderType::tryFrom($state)) {
                        ReminderType::Upcoming => 'warning',
                        ReminderType::Overdue => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('announcements.channel'))
                    ->formatStateUsing(fn ($state) => __('announcements.channel_' . ($state instanceof \BackedEnum ? $state->value : $state)))
                    ->badge()
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state : ReminderChannel::tryFrom($state)) {
                        ReminderChannel::Email => 'info',
                        ReminderChannel::Sms => 'success',
                        ReminderChannel::Push => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('announcements.sent_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reminder_type')
                    ->label(__('announcements.reminder_type'))
                    ->options(collect(ReminderType::cases())->mapWithKeys(fn ($c) => [$c->value => __('announcements.reminder_type_' . $c->value)])),
                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('announcements.channel'))
                    ->options(collect(ReminderChannel::cases())->mapWithKeys(fn ($c) => [$c->value => __('announcements.channel_' . $c->value)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('sent_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => PaymentReminderResource\Pages\ListPaymentReminders::route('/'),
        ];
    }
}
