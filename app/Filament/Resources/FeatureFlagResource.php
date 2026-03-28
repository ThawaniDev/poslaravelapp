<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\FeatureFlag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('settings.feature_flags');
    }

    public static function getModelLabel(): string
    {
        return __('settings.feature_flag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.feature_flags');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.feature_flags']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.flag_details'))
                ->schema([
                    Forms\Components\TextInput::make('flag_key')
                        ->label(__('settings.flag_key'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->alphaNum()
                        ->helperText(__('settings.flag_key_helper')),
                    Forms\Components\Textarea::make('description')
                        ->label(__('settings.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.rollout_config'))
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label(__('settings.is_enabled'))
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('rollout_percentage')
                        ->label(__('settings.rollout_percentage'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(100)
                        ->suffix('%')
                        ->visible(fn (Forms\Get $get) => $get('is_enabled')),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.targeting'))
                ->schema([
                    Forms\Components\TagsInput::make('target_plan_ids')
                        ->label(__('settings.target_plan_ids'))
                        ->placeholder(__('settings.enter_plan_ids'))
                        ->helperText(__('settings.targeting_helper')),
                    Forms\Components\TagsInput::make('target_store_ids')
                        ->label(__('settings.target_store_ids'))
                        ->placeholder(__('settings.enter_store_ids'))
                        ->helperText(__('settings.targeting_helper')),
                ])->columns(2)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('flag_key')
                    ->label(__('settings.flag_key'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('settings.description'))
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('settings.is_enabled'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rollout_percentage')
                    ->label(__('settings.rollout_percentage'))
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('settings.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('settings.is_enabled')),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn ($record) => $record->is_enabled ? __('settings.disable') : __('settings.enable'))
                    ->icon(fn ($record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_enabled' => !$record->is_enabled]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: $record->is_enabled ? 'enable_feature_flag' : 'disable_feature_flag',
                            entityType: 'feature_flag',
                            entityId: $record->id,
                            details: ['flag_key' => $record->flag_key],
                        );
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_feature_flag',
                            entityType: 'feature_flag',
                            entityId: $record->id,
                            details: ['flag_key' => $record->flag_key],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => FeatureFlagResource\Pages\ListFeatureFlags::route('/'),
            'create' => FeatureFlagResource\Pages\CreateFeatureFlag::route('/create'),
            'edit' => FeatureFlagResource\Pages\EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
