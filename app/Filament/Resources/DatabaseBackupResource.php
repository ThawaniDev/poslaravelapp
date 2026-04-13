<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\BackupSync\Enums\DatabaseBackupStatus;
use App\Domain\BackupSync\Enums\DatabaseBackupType;
use App\Domain\BackupSync\Models\DatabaseBackup;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

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

    public static function getNavigationBadge(): ?string
    {
        $failed = DatabaseBackup::where('status', DatabaseBackupStatus::Failed)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('infrastructure.backup_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('backup_type')
                            ->label(__('infrastructure.backup_type'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => __('infrastructure.backup_type_' . ($state->value ?? $state)))
                            ->color(fn ($state) => match ($state) {
                                DatabaseBackupType::Automated => 'success',
                                DatabaseBackupType::AutoDaily => 'primary',
                                DatabaseBackupType::AutoWeekly => 'info',
                                DatabaseBackupType::Manual => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('infrastructure.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => __('infrastructure.backup_status_' . ($state->value ?? $state)))
                            ->color(fn ($state) => match ($state) {
                                DatabaseBackupStatus::Completed => 'success',
                                DatabaseBackupStatus::InProgress => 'warning',
                                DatabaseBackupStatus::Failed => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('file_size_bytes')
                            ->label(__('infrastructure.file_size'))
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 2) . ' MB' : '-'),
                        Infolists\Components\TextEntry::make('duration')
                            ->label(__('infrastructure.duration'))
                            ->getStateUsing(function ($record) {
                                if ($record->started_at && $record->completed_at) {
                                    return $record->started_at->diffForHumans($record->completed_at, ['parts' => 2, 'short' => true]);
                                }
                                return '-';
                            }),
                    ])->columns(4),

                Infolists\Components\Section::make(__('infrastructure.timing'))
                    ->schema([
                        Infolists\Components\TextEntry::make('started_at')
                            ->label(__('infrastructure.started_at'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label(__('infrastructure.completed_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('triggered_by')
                            ->label(__('infrastructure.triggered_by'))
                            ->placeholder(__('infrastructure.automated')),
                    ])->columns(3),

                Infolists\Components\Section::make(__('infrastructure.file_info'))
                    ->schema([
                        Infolists\Components\TextEntry::make('file_path')
                            ->label(__('infrastructure.file_path'))
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('checksum')
                            ->label(__('infrastructure.checksum'))
                            ->fontFamily('mono')
                            ->visible(fn ($record) => ! empty($record->checksum)),
                        Infolists\Components\TextEntry::make('tables_count')
                            ->label(__('infrastructure.tables_count'))
                            ->visible(fn ($record) => $record->tables_count > 0),
                        Infolists\Components\TextEntry::make('rows_count')
                            ->label(__('infrastructure.rows_count'))
                            ->numeric()
                            ->visible(fn ($record) => $record->rows_count > 0),
                    ])->columns(3),

                Infolists\Components\Section::make(__('infrastructure.notes'))
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('infrastructure.notes'))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => ! empty($record->notes)),

                Infolists\Components\Section::make(__('infrastructure.error'))
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label(__('infrastructure.error_message'))
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => ! empty($record->error_message)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('backup_type')
                    ->label(__('infrastructure.backup_type'))
                    ->formatStateUsing(fn ($state) => __('infrastructure.backup_type_' . ($state->value ?? $state)))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        DatabaseBackupType::Automated => 'success',
                        DatabaseBackupType::AutoDaily => 'primary',
                        DatabaseBackupType::AutoWeekly => 'info',
                        DatabaseBackupType::Manual => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('infrastructure.status'))
                    ->formatStateUsing(fn ($state) => __('infrastructure.backup_status_' . ($state->value ?? $state)))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        DatabaseBackupStatus::Completed => 'success',
                        DatabaseBackupStatus::InProgress => 'warning',
                        DatabaseBackupStatus::Failed => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_size_bytes')
                    ->label(__('infrastructure.file_size'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1048576, 2) . ' MB' : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label(__('infrastructure.duration'))
                    ->getStateUsing(function ($record) {
                        if ($record->started_at && $record->completed_at) {
                            $diff = $record->started_at->diffInSeconds($record->completed_at);
                            return $diff < 60 ? "{$diff}s" : round($diff / 60, 1) . 'm';
                        }
                        return '-';
                    }),
                Tables\Columns\TextColumn::make('tables_count')
                    ->label(__('infrastructure.tables_count'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('file_path')
                    ->label(__('infrastructure.file_path'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('infrastructure.error'))
                    ->limit(40)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('infrastructure.started_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('infrastructure.completed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('infrastructure.status'))
                    ->options(collect(DatabaseBackupStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('infrastructure.backup_status_' . $c->value)])),
                Tables\Filters\SelectFilter::make('backup_type')
                    ->label(__('infrastructure.backup_type'))
                    ->options(collect(DatabaseBackupType::cases())->mapWithKeys(fn ($c) => [$c->value => __('infrastructure.backup_type_' . $c->value)])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('trigger_backup')
                    ->label(__('infrastructure.trigger_backup'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('infrastructure.manage'))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('infrastructure.notes'))
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription(__('infrastructure.trigger_backup_confirm'))
                    ->action(function (array $data) {
                        $backup = DatabaseBackup::create([
                            'backup_type' => DatabaseBackupType::Manual,
                            'status' => DatabaseBackupStatus::InProgress,
                            'started_at' => now(),
                            'triggered_by' => auth('admin')->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'trigger_manual_backup',
                            entityType: 'database_backup',
                            entityId: $backup->id,
                            details: ['notes' => $data['notes'] ?? null],
                        );

                        // Run backup in background via Artisan if available
                        try {
                            $dbName = config('database.connections.pgsql.database', 'pos');
                            $dumpPath = storage_path("app/backups/manual_{$backup->id}.sql.gz");
                            $dir = dirname($dumpPath);
                            if (! is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }

                            $host = config('database.connections.pgsql.host', '127.0.0.1');
                            $port = config('database.connections.pgsql.port', '5432');
                            $user = config('database.connections.pgsql.username', 'postgres');

                            $result = Process::timeout(300)
                                ->env(['PGPASSWORD' => config('database.connections.pgsql.password', '')])
                                ->run("pg_dump -h {$host} -p {$port} -U {$user} {$dbName} | gzip > {$dumpPath}");

                            if ($result->successful() && file_exists($dumpPath)) {
                                $tablesCount = (int) DB::selectOne("SELECT count(*) as cnt FROM information_schema.tables WHERE table_schema = 'public'")?->cnt;
                                $backup->update([
                                    'status' => DatabaseBackupStatus::Completed,
                                    'completed_at' => now(),
                                    'file_path' => $dumpPath,
                                    'file_size_bytes' => filesize($dumpPath),
                                    'checksum' => md5_file($dumpPath),
                                    'tables_count' => $tablesCount,
                                ]);
                            } else {
                                $backup->update([
                                    'status' => DatabaseBackupStatus::Failed,
                                    'completed_at' => now(),
                                    'error_message' => $result->errorOutput() ?: __('settings.pg_dump_failed'),
                                ]);
                            }
                        } catch (\Throwable $e) {
                            $backup->update([
                                'status' => DatabaseBackupStatus::Failed,
                                'completed_at' => now(),
                                'error_message' => $e->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title(__('infrastructure.backup_triggered'))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label(__('infrastructure.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (DatabaseBackup $record) => $record->status === DatabaseBackupStatus::Completed && $record->file_path && file_exists($record->file_path))
                    ->action(function (DatabaseBackup $record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'download_backup',
                            entityType: 'database_backup',
                            entityId: $record->id,
                        );

                        return response()->download($record->file_path);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('infrastructure.manage'))
                    ->before(function (DatabaseBackup $record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_backup',
                            entityType: 'database_backup',
                            entityId: $record->id,
                            details: ['file_path' => $record->file_path],
                        );
                        // Remove file from disk
                        if ($record->file_path && file_exists($record->file_path)) {
                            @unlink($record->file_path);
                        }
                    }),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => DatabaseBackupResource\Pages\ListDatabaseBackups::route('/'),
            'view' => DatabaseBackupResource\Pages\ViewDatabaseBackup::route('/{record}'),
        ];
    }
}
