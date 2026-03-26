<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\BackupSync\Enums\AppReleaseChannel;
use App\Domain\BackupSync\Enums\AppReleasePlatform;
use App\Domain\BackupSync\Enums\AppSubmissionStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppReleaseResource extends Resource
{
    protected static ?string $model = AppRelease::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-circle';

    protected static ?string $navigationGroup = 'Updates';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('updates.app_releases');
    }

    public static function getModelLabel(): string
    {
        return __('updates.app_release');
    }

    public static function getPluralModelLabel(): string
    {
        return __('updates.app_releases');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['app_updates.view', 'app_updates.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('updates.release_info'))
                ->schema([
                    Forms\Components\TextInput::make('version_number')
                        ->label(__('updates.version_number'))
                        ->required()
                        ->maxLength(30)
                        ->placeholder('1.2.3'),
                    Forms\Components\TextInput::make('build_number')
                        ->label(__('updates.build_number'))
                        ->maxLength(30),
                    Forms\Components\Select::make('platform')
                        ->label(__('updates.platform'))
                        ->options(collect(AppReleasePlatform::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.platform_' . $c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('channel')
                        ->label(__('updates.channel'))
                        ->options(collect(AppReleaseChannel::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.channel_' . $c->value)]))
                        ->required()
                        ->native(false),
                ])->columns(2),

            Forms\Components\Section::make(__('updates.release_notes'))
                ->schema([
                    Forms\Components\RichEditor::make('release_notes')
                        ->label(__('updates.notes_en'))
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('release_notes_ar')
                        ->label(__('updates.notes_ar'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('updates.distribution'))
                ->schema([
                    Forms\Components\TextInput::make('download_url')
                        ->label(__('updates.download_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\TextInput::make('store_url')
                        ->label(__('updates.store_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\Select::make('submission_status')
                        ->label(__('updates.submission_status'))
                        ->options(collect(AppSubmissionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.sub_status_' . $c->value)]))
                        ->default('not_applicable')
                        ->native(false),
                    Forms\Components\TextInput::make('min_supported_version')
                        ->label(__('updates.min_supported_version'))
                        ->maxLength(30)
                        ->placeholder('1.0.0'),
                ])->columns(2),

            Forms\Components\Section::make(__('updates.rollout'))
                ->schema([
                    Forms\Components\TextInput::make('rollout_percentage')
                        ->label(__('updates.rollout_percentage'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(100)
                        ->suffix('%'),
                    Forms\Components\Toggle::make('is_force_update')
                        ->label(__('updates.force_update'))
                        ->helperText(__('updates.force_update_helper')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('updates.is_active'))
                        ->default(true),
                    Forms\Components\DateTimePicker::make('released_at')
                        ->label(__('updates.released_at'))
                        ->native(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')
                    ->label(__('updates.version'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('platform')
                    ->label(__('updates.platform'))
                    ->formatStateUsing(fn ($state) => __('updates.platform_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AppReleasePlatform::Android => 'success',
                        AppReleasePlatform::Ios => 'gray',
                        AppReleasePlatform::Windows => 'info',
                        AppReleasePlatform::Macos => 'primary',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('updates.channel'))
                    ->formatStateUsing(fn ($state) => __('updates.channel_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AppReleaseChannel::Stable => 'success',
                        AppReleaseChannel::Beta => 'warning',
                        AppReleaseChannel::Testflight => 'info',
                        AppReleaseChannel::InternalTest => 'gray',
                    }),
                Tables\Columns\TextColumn::make('submission_status')
                    ->label(__('updates.status'))
                    ->formatStateUsing(fn ($state) => $state ? __('updates.sub_status_' . $state->value) : '-')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AppSubmissionStatus::Live => 'success',
                        AppSubmissionStatus::Approved => 'info',
                        AppSubmissionStatus::InReview => 'warning',
                        AppSubmissionStatus::Rejected => 'danger',
                        AppSubmissionStatus::Submitted => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rollout_percentage')
                    ->label(__('updates.rollout'))
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_force_update')
                    ->label(__('updates.force'))
                    ->boolean()
                    ->trueColor('danger')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('updates.active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('app_update_stats_count')
                    ->counts('appUpdateStats')
                    ->label(__('updates.installs'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('released_at')
                    ->label(__('updates.released_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label(__('updates.platform'))
                    ->options(collect(AppReleasePlatform::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.platform_' . $c->value)])),
                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('updates.channel'))
                    ->options(collect(AppReleaseChannel::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.channel_' . $c->value)])),
                Tables\Filters\SelectFilter::make('submission_status')
                    ->label(__('updates.status'))
                    ->options(collect(AppSubmissionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('updates.sub_status_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('updates.active')),
            ])
            ->actions([
                Tables\Actions\Action::make('rollout')
                    ->label(__('updates.adjust_rollout'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('rollout_percentage')
                            ->label(__('updates.rollout_percentage'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%'),
                    ])
                    ->fillForm(fn ($record) => ['rollout_percentage' => $record->rollout_percentage])
                    ->action(function ($record, array $data) {
                        $old = $record->rollout_percentage;
                        $record->update(['rollout_percentage' => $data['rollout_percentage']]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'adjust_rollout',
                            entityType: 'app_release',
                            entityId: $record->id,
                            details: ['version' => $record->version_number, 'from' => $old, 'to' => $data['rollout_percentage']],
                        );
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_app_release',
                            entityType: 'app_release',
                            entityId: $record->id,
                            details: ['version' => $record->version_number, 'platform' => $record->platform->value],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AppReleaseResource\Pages\ListAppReleases::route('/'),
            'create' => AppReleaseResource\Pages\CreateAppRelease::route('/create'),
            'edit' => AppReleaseResource\Pages\EditAppRelease::route('/{record}/edit'),
        ];
    }
}
