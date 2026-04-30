<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AppointmentConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeAppointmentConfig';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Default Appointment Booking Config');
    }

    protected static ?string $icon = 'heroicon-o-calendar-days';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Booking Window'))
                ->schema([
                    Forms\Components\Select::make('default_slot_duration_minutes')
                        ->label(__('Default Slot Duration'))
                        ->options([
                            15  => '15 min',
                            30  => '30 min',
                            45  => '45 min',
                            60  => '60 min (1 hr)',
                            90  => '90 min (1.5 hr)',
                            120 => '120 min (2 hr)',
                        ])
                        ->default(30)
                        ->required(),
                    Forms\Components\TextInput::make('min_advance_booking_hours')
                        ->label(__('Min Advance Booking (hours)'))
                        ->helperText(__('Customer must book at least N hours ahead'))
                        ->numeric()
                        ->default(2)
                        ->minValue(0),
                    Forms\Components\TextInput::make('max_advance_booking_days')
                        ->label(__('Max Advance Booking (days)'))
                        ->helperText(__('How far in advance customers can book'))
                        ->numeric()
                        ->default(30)
                        ->minValue(1),
                    Forms\Components\TextInput::make('overbooking_buffer_percentage')
                        ->label(__('Overbooking Buffer (%)'))
                        ->helperText(__('Allow slight overbooking; 0 = disabled'))
                        ->numeric()
                        ->default(0)
                        ->step(1)
                        ->minValue(0),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Cancellation Policy'))
                ->schema([
                    Forms\Components\TextInput::make('cancellation_window_hours')
                        ->label(__('Free Cancellation Window (hours before)'))
                        ->helperText(__('Cancellations before this window are free'))
                        ->numeric()
                        ->default(24)
                        ->minValue(0),
                    Forms\Components\Select::make('cancellation_fee_type')
                        ->label(__('Cancellation Fee Type'))
                        ->options([
                            'none'       => __('None'),
                            'fixed'      => __('Fixed (SAR)'),
                            'percentage' => __('Percentage of Total'),
                        ])
                        ->default('none')
                        ->live(),
                    Forms\Components\TextInput::make('cancellation_fee_value')
                        ->label(__('Cancellation Fee Value'))
                        ->numeric()
                        ->default(0)
                        ->visible(fn (Forms\Get $get) => $get('cancellation_fee_type') !== 'none'),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Walk-ins & Deposit'))
                ->schema([
                    Forms\Components\Toggle::make('allow_walkins')
                        ->label(__('Allow Walk-in Customers'))
                        ->helperText(__('Accept non-booked customers'))
                        ->default(true),
                    Forms\Components\Toggle::make('require_deposit')
                        ->label(__('Require Deposit'))
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('deposit_percentage')
                        ->label(__('Deposit Percentage (%)'))
                        ->numeric()
                        ->default(0)
                        ->visible(fn (Forms\Get $get) => (bool) $get('require_deposit')),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Service Category Templates'))
                ->description(__('Default service categories seeded to new stores of this type.'))
                ->schema([
                    Forms\Components\Repeater::make('service_categories_inline')
                        ->label(__('Service Categories'))
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('Name (EN)'))
                                ->required(),
                            Forms\Components\TextInput::make('name_ar')
                                ->label(__('Name (AR)'))
                                ->required(),
                            Forms\Components\TextInput::make('default_duration_minutes')
                                ->label(__('Duration (min)'))
                                ->numeric()
                                ->default(30),
                            Forms\Components\TextInput::make('default_price')
                                ->label(__('Price (SAR)'))
                                ->numeric()
                                ->nullable(),
                        ])
                        ->columns(4)
                        ->addActionLabel(__('Add Service Category'))
                        ->columnSpanFull()
                        ->helperText(__('These are seeded when the store enables appointment booking')),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('default_slot_duration_minutes')
                    ->label(__('Default Slot'))
                    ->formatStateUsing(fn ($state) => "{$state} min"),
                Tables\Columns\TextColumn::make('min_advance_booking_hours')
                    ->label(__('Min Advance'))
                    ->formatStateUsing(fn ($state) => "{$state} hrs"),
                Tables\Columns\TextColumn::make('max_advance_booking_days')
                    ->label(__('Max Advance'))
                    ->formatStateUsing(fn ($state) => "{$state} days"),
                Tables\Columns\TextColumn::make('cancellation_window_hours')
                    ->label(__('Cancellation Window'))
                    ->formatStateUsing(fn ($state) => "{$state} hrs"),
                Tables\Columns\TextColumn::make('cancellation_fee_type')
                    ->label(__('Cancel Fee'))
                    ->badge()
                    ->color(fn ($state) => $state === 'none' ? 'success' : 'warning'),
                Tables\Columns\IconColumn::make('allow_walkins')
                    ->label(__('Walk-ins'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('require_deposit')
                    ->label(__('Deposit'))
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Set Appointment Config')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
