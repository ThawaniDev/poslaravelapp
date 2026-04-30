<?php

namespace App\Filament\Resources;

use App\Domain\AppUpdateManagement\Models\AppUpdateStat;
use App\Domain\BackupSync\Enums\AppUpdateStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppUpdateStatResource extends Resource
{
    protected static ?string $model = AppUpdateStat::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_updates');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('updates.update_stats');
    }

    public static function getModelLabel(): string
    {
        return __('updates.update_stat');
    }

    public static function getPluralModelLabel(): string
    {
        return __('updates.update_stats');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['app_updates.view']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('updates.stat_record_details'))
                    ->schema([
                        Forms\Components\TextInput::make('store_id')
                            ->label(__('updates.store'))
                            ->disabled(),
                        Forms\Components\Select::make('app_release_id')
                            ->label(__('updates.release'))
                            ->relationship('appRelease', 'version_number')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->label(__('updates.status'))
                            ->options(
                                collect(AppUpdateStatus::cases())
                                    ->mapWithKeys(fn ($c) => [$c->value => __('updates.stat_status_' . $c->value)])
                            )
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make(__('updates.error_details'))
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label(__('updates.error_message'))
                            ->rows(4)
                            ->disabled(),
                    ])
                    ->visible(fn ($record) => filled($record?->error_message)),

                Forms\Components\Section::make(__('common.timestamps'))
                    ->schema([
                        Forms\Components\TextInput::make('created_at')
                            ->label(__('common.created_at'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
                        Forms\Components\TextInput::make('updated_at')
                            ->label(__('common.updated_at'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
                    ])->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('appRelease.version_number')
                    ->label(__('updates.version'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('appRelease.platform')
                    ->label(__('updates.platform'))
                    ->formatStateUsing(fn ($state) => $state ? __('updates.platform_' . $state->value) : '-')
                    ->badge(),
                Tables\Columns\TextColumn::make('store_id')
                    ->label(__('updates.store'))
                    ->limit(12)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('updates.status'))
                    ->formatStateUsing(fn ($state) => __('updates.stat_status_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AppUpdateStatus::Installed => 'success',
                        AppUpdateStatus::Downloaded => 'info',
                        AppUpdateStatus::Downloading => 'warning',
                        AppUpdateStatus::Pending => 'gray',
                        AppUpdateStatus::Failed => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('updates.error'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('updates.status'))
                    ->options(collect(AppUpdateStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.stat_status_' . $c->value)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('appRelease');
    }

    public static function getPages(): array
    {
        return [
            'index' => AppUpdateStatResource\Pages\ListAppUpdateStats::route('/'),
            'view' => AppUpdateStatResource\Pages\ViewAppUpdateStat::route('/{record}'),
        ];
    }
}
