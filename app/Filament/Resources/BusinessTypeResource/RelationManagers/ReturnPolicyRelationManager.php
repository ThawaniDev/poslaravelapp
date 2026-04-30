<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReturnPolicyRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeReturnPolicy';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Default Return Policy');
    }

    protected static ?string $icon = 'heroicon-o-arrow-uturn-left';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Return Window'))
                ->schema([
                    Forms\Components\TextInput::make('return_window_days')
                        ->label(__('Return Window (days)'))
                        ->helperText(__('Set to 0 to disallow returns entirely'))
                        ->numeric()
                        ->default(14)
                        ->minValue(0)
                        ->required(),
                    Forms\Components\TextInput::make('void_grace_period_minutes')
                        ->label(__('Void Grace Period (minutes)'))
                        ->helperText(__('Time after sale within which cashier can void without return flow'))
                        ->numeric()
                        ->default(5)
                        ->minValue(0),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Refund Settings'))
                ->schema([
                    Forms\Components\CheckboxList::make('refund_methods')
                        ->label(__('Allowed Refund Methods'))
                        ->options([
                            'original_payment' => __('Original Payment Method'),
                            'store_credit'     => __('Store Credit'),
                            'cash'             => __('Cash'),
                            'exchange_only'    => __('Exchange Only'),
                        ])
                        ->columns(2)
                        ->default(['original_payment'])
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('restocking_fee_percentage')
                        ->label(__('Restocking Fee (%)'))
                        ->helperText(__('0 = no fee'))
                        ->numeric()
                        ->default(0)
                        ->step(0.5)
                        ->minValue(0)
                        ->maxValue(100),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Approval & Requirements'))
                ->schema([
                    Forms\Components\Toggle::make('require_receipt')
                        ->label(__('Require Receipt for Returns'))
                        ->default(true),
                    Forms\Components\Toggle::make('return_reason_required')
                        ->label(__('Require Return Reason'))
                        ->default(true),
                    Forms\Components\Toggle::make('partial_return_allowed')
                        ->label(__('Allow Partial Returns'))
                        ->default(true),
                    Forms\Components\Toggle::make('require_manager_approval')
                        ->label(__('Require Manager Approval'))
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('max_return_without_approval')
                        ->label(__('Max Return Value Without Approval (SAR)'))
                        ->helperText(__('0 = always requires approval if toggle is on'))
                        ->numeric()
                        ->default(0)
                        ->visible(fn (Forms\Get $get) => (bool) $get('require_manager_approval')),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('return_window_days')
                    ->label(__('Return Window'))
                    ->formatStateUsing(fn ($state) => $state === 0 ? __('No Returns') : "{$state} " . __('days')),
                Tables\Columns\TextColumn::make('void_grace_period_minutes')
                    ->label(__('Void Grace Period'))
                    ->formatStateUsing(fn ($state) => "{$state} min"),
                Tables\Columns\TextColumn::make('restocking_fee_percentage')
                    ->label(__('Restocking Fee'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state}%" : __('None')),
                Tables\Columns\IconColumn::make('require_receipt')
                    ->label(__('Receipt Required'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('require_manager_approval')
                    ->label(__('Manager Approval'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('partial_return_allowed')
                    ->label(__('Partial Returns'))
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Set Return Policy')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
