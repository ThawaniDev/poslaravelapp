<?php

namespace App\Filament\Resources;

use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Enums\DatabaseBackupType;
use App\Domain\BackupSync\Models\DatabaseBackup;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DatabaseBackupResource extends Resource
{
    protected static ?string $model = DatabaseBackup::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_infrastructure');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('infrastructure.database_backups');
    }

    public static function getModelLabel(): string
    {
        return __('infrastructure.database_backup');
    }

    public static function getPluralModelLabel(): string
    {
        return __('infrastructure.database_backups');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['infrastructure.view', 'infrastructure.manage']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('backup_type')
                    ->label(__('infrastructure.backup_type'))
                    ->formatStateUsing(fn ($state) => __('infrastructure.backup_type_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        DatabaseBackupType::Automated => 'success',
                        DatabaseBackupType::AutoDaily => 'primary',
                        DatabaseBackupType::AutoWeekly => 'info',
                        DatabaseBackupType::Manual => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('infrastructure.status'))
                    ->formatStateUsing(fn ($state) => __('infrastructure.backup_status_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        DatabaseBackupStatus::Completed => 'success',
                        DatabaseBackupStatus::InProgress => 'warning',
                        DatabaseBackupStatus::Failed => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_size_bytes')
                    ->label(__('infrastructure.file_size'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 2) . ' MB' : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_path')
                    ->label(__('infrastructure.file_path'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('infrastructure.error'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('infrastructure.started_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('infrastructure.completed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('infrastructure.status'))
                    ->options(collect(DatabaseBackupStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('infrastructure.backup_status_' . $c->value)])),
                Tables\Filters\SelectFilter::make('backup_type')
                    ->label(__('infrastructure.backup_type'))
                    ->options(collect(DatabaseBackupType::cases())->mapWithKeys(fn ($c) => [$c->value => __('infrastructure.backup_type_' . $c->value)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => DatabaseBackupResource\Pages\ListDatabaseBackups::route('/'),
        ];
    }
}
