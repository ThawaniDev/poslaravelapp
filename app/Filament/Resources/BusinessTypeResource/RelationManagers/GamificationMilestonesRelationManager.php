<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GamificationMilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeGamificationMilestones';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Gamification Milestone Templates');
    }

    protected static ?string $icon = 'heroicon-o-flag';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Milestone Name (EN)'))
                ->required()
                ->maxLength(100)
                ->placeholder(__('ui.placeholder_first_1000_sar')),
            Forms\Components\TextInput::make('name_ar')
                ->label(__('Milestone Name (AR)'))
                ->required()
                ->maxLength(100)
                ->placeholder('أول ألف ريال إنفاقاً'),
            Forms\Components\Select::make('milestone_type')
                ->label(__('Milestone Type'))
                ->options([
                    'total_spend'     => __('Total Spend (SAR)'),
                    'total_visits'    => __('Total Visits'),
                    'membership_days' => __('Membership Days'),
                ])
                ->required(),
            Forms\Components\TextInput::make('threshold_value')
                ->label(__('Threshold Value'))
                ->helperText(__('e.g., 1000 SAR, 50 visits, 365 days'))
                ->numeric()
                ->default(1000)
                ->required(),
            Forms\Components\Select::make('reward_type')
                ->label(__('Reward Type'))
                ->options([
                    'points'              => __('Bonus Points'),
                    'discount_percentage' => __('Discount %'),
                    'tier_upgrade'        => __('Tier Upgrade'),
                    'badge'               => __('Badge Award'),
                ])
                ->default('points')
                ->required(),
            Forms\Components\TextInput::make('reward_value')
                ->label(__('Reward Value'))
                ->helperText(__('Points amount, discount %, tier name, or badge name'))
                ->default('500')
                ->required(),
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
                Tables\Columns\TextColumn::make('milestone_type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'total_spend'     => __('Total Spend'),
                        'total_visits'    => __('Total Visits'),
                        'membership_days' => __('Membership Days'),
                        default           => $state,
                    }),
                Tables\Columns\TextColumn::make('threshold_value')
                    ->label(__('Threshold'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('reward_type')
                    ->label(__('Reward'))
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('reward_value')
                    ->label(__('Value')),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Sort'))
                    ->sortable(),
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
