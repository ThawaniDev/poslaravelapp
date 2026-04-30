<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LoyaltyConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeLoyaltyConfig';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Default Loyalty Program Config');
    }

    protected static ?string $icon = 'heroicon-o-star';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Program Type'))
                ->schema([
                    Forms\Components\Select::make('program_type')
                        ->label(__('Program Type'))
                        ->options([
                            'points'   => __('Points'),
                            'stamps'   => __('Stamps Card'),
                            'cashback' => __('Cashback'),
                            'none'     => __('None (No Loyalty Program)'),
                        ])
                        ->default('points')
                        ->required()
                        ->live(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Active by Default'))
                        ->helperText(__('If off, the loyalty program is seeded as inactive. Provider must enable it.'))
                        ->default(false),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Points Settings'))
                ->schema([
                    Forms\Components\TextInput::make('earning_rate')
                        ->label(__('Earning Rate (points per SAR)'))
                        ->numeric()
                        ->default(1.0)
                        ->step(0.1)
                        ->minValue(0.01),
                    Forms\Components\TextInput::make('redemption_value')
                        ->label(__('Redemption Value (SAR per point)'))
                        ->numeric()
                        ->default(0.01)
                        ->step(0.001)
                        ->minValue(0),
                    Forms\Components\TextInput::make('min_redemption_points')
                        ->label(__('Min Redemption Points'))
                        ->numeric()
                        ->default(100)
                        ->minValue(0),
                    Forms\Components\TextInput::make('points_expiry_days')
                        ->label(__('Points Expiry (days, 0 = never)'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                ])
                ->columns(2)
                ->visible(fn (Forms\Get $get) => in_array($get('program_type'), ['points', 'cashback'])),

            Forms\Components\Section::make(__('Stamps Settings'))
                ->schema([
                    Forms\Components\TextInput::make('stamps_card_size')
                        ->label(__('Stamps for 1 Reward'))
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->helperText(__('e.g., 10 stamps = 1 free item')),
                ])
                ->visible(fn (Forms\Get $get) => $get('program_type') === 'stamps'),

            Forms\Components\Section::make(__('Cashback Settings'))
                ->schema([
                    Forms\Components\TextInput::make('cashback_percentage')
                        ->label(__('Cashback Percentage (%)'))
                        ->numeric()
                        ->default(1.0)
                        ->step(0.5)
                        ->minValue(0),
                ])
                ->visible(fn (Forms\Get $get) => $get('program_type') === 'cashback'),

            Forms\Components\Section::make(__('Tier Program'))
                ->schema([
                    Forms\Components\Toggle::make('enable_tiers')
                        ->label(__('Enable Tier System'))
                        ->default(false)
                        ->live(),
                    Forms\Components\Repeater::make('tier_definitions')
                        ->label(__('Tier Definitions'))
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('Tier Name (EN)'))
                                ->required()
                                ->placeholder('Silver'),
                            Forms\Components\TextInput::make('name_ar')
                                ->label(__('Tier Name (AR)'))
                                ->placeholder('فضي'),
                            Forms\Components\TextInput::make('min_points')
                                ->label(__('Min Points'))
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('multiplier')
                                ->label(__('Points Multiplier'))
                                ->numeric()
                                ->default(1.0)
                                ->step(0.1),
                        ])
                        ->columns(4)
                        ->addActionLabel(__('Add Tier'))
                        ->columnSpanFull()
                        ->visible(fn (Forms\Get $get) => (bool) $get('enable_tiers')),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('program_type')
                    ->label(__('Program Type'))
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'points'   => 'success',
                        'stamps'   => 'info',
                        'cashback' => 'warning',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('earning_rate')
                    ->label(__('Earn Rate'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) . ' pts/SAR' : '—'),
                Tables\Columns\TextColumn::make('redemption_value')
                    ->label(__('Redemption'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 4) . ' SAR/pt' : '—'),
                Tables\Columns\IconColumn::make('enable_tiers')
                    ->label(__('Tiers'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Set Loyalty Config')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
