<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GamificationChallengesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeGamificationChallenges';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Gamification Challenge Templates');
    }

    protected static ?string $icon = 'heroicon-o-trophy';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Challenge Name (EN)'))
                ->required()
                ->maxLength(100)
                ->placeholder(__('ui.placeholder_weekend_warrior')),
            Forms\Components\TextInput::make('name_ar')
                ->label(__('Challenge Name (AR)'))
                ->required()
                ->maxLength(100)
                ->placeholder('محارب نهاية الأسبوع'),
            Forms\Components\Select::make('challenge_type')
                ->label(__('Challenge Type'))
                ->options([
                    'buy_x_get_y'    => __('Buy X Get Y'),
                    'spend_target'   => __('Spend Target'),
                    'visit_streak'   => __('Visit Streak'),
                    'category_spend' => __('Category Spend'),
                    'referral'       => __('Referral'),
                ])
                ->required()
                ->live(),
            Forms\Components\TextInput::make('target_value')
                ->label(__('Target Value'))
                ->helperText(__('e.g., 5 purchases, 500 SAR, 4 weekends'))
                ->numeric()
                ->default(1)
                ->required(),
            Forms\Components\Select::make('reward_type')
                ->label(__('Reward Type'))
                ->options([
                    'points'              => __('Bonus Points'),
                    'discount_percentage' => __('Discount %'),
                    'free_item'           => __('Free Item'),
                    'badge'               => __('Badge'),
                ])
                ->default('points')
                ->required(),
            Forms\Components\TextInput::make('reward_value')
                ->label(__('Reward Value'))
                ->helperText(__('Points amount, discount %, SKU for free item, or badge name'))
                ->default('100')
                ->required(),
            Forms\Components\TextInput::make('duration_days')
                ->label(__('Challenge Duration (days)'))
                ->numeric()
                ->default(30)
                ->minValue(1),
            Forms\Components\Toggle::make('is_recurring')
                ->label(__('Recurring (resets after completion)'))
                ->default(false),
            Forms\Components\Textarea::make('description')
                ->label(__('Description (EN)'))
                ->rows(2)
                ->placeholder('Visit us 4 weekends in a row and earn 200 bonus points'),
            Forms\Components\Textarea::make('description_ar')
                ->label(__('Description (AR)'))
                ->rows(2),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('Sort Order'))
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name (EN)'))
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->name_ar),
                Tables\Columns\TextColumn::make('challenge_type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('target_value')
                    ->label(__('Target')),
                Tables\Columns\TextColumn::make('reward_type')
                    ->label(__('Reward'))
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('reward_value')
                    ->label(__('Value')),
                Tables\Columns\TextColumn::make('duration_days')
                    ->label(__('Days'))
                    ->formatStateUsing(fn ($state) => "{$state}d"),
                Tables\Columns\IconColumn::make('is_recurring')
                    ->label(__('Recurring'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
