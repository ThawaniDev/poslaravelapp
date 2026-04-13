<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemHealthCheckResource extends Resource
{
    protected static ?string $model = SystemHealthCheck::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_infrastructure');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('infrastructure.health_checks');
    }

    public static function getModelLabel(): string
    {
        return __('infrastructure.health_check');
    }

    public static function getPluralModelLabel(): string
    {
        return __('infrastructure.health_checks');
    }

    public static function getNavigationBadge(): ?string
    {
        $critical = SystemHealthCheck::latest('checked_at')
            ->get()
            ->unique('service')
            ->where('status', 'critical')
            ->count();

        return $critical > 0 ? (string) $critical : null;
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
                Infolists\Components\Section::make(__('infrastructure.health_check'))
                    ->schema([
                        Infolists\Components\TextEntry::make('service')
                            ->label(__('infrastructure.service'))
                            ->weight('bold')
                            ->size('lg'),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('infrastructure.status'))
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'healthy' => 'success',
                                'warning' => 'warning',
                                'critical' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('response_time_ms')
                            ->label(__('infrastructure.response_time'))
                            ->suffix(' ms')
                            ->color(fn (int $state) => match (true) {
                                $state < 100 => 'success',
                                $state < 500 => 'warning',
                                default => 'danger',
                            }),
                        Infolists\Components\TextEntry::make('checked_at')
                            ->label(__('infrastructure.checked_at'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('triggered_by')
                            ->label(__('infrastructure.triggered_by'))
                            ->placeholder(__('infrastructure.automated')),
                    ])->columns(3),

                Infolists\Components\Section::make(__('infrastructure.details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('details')
                            ->label(__('infrastructure.details'))
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : ($state ?? '-'))
                            ->markdown()
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label(__('infrastructure.error_message'))
                            ->color('danger')
                            ->visible(fn ($record) => ! empty($record->error_message))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service')
                    ->label(__('infrastructure.service'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('infrastructure.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'healthy' => 'success',
                        'warning' => 'warning',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_time_ms')
                    ->label(__('infrastructure.response_time'))
                    ->suffix(' ms')
                    ->sortable()
                    ->color(fn (int $state) => match (true) {
                        $state < 100 => 'success',
                        $state < 500 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('infrastructure.error_message'))
                    ->limit(40)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('triggered_by')
                    ->label(__('infrastructure.triggered_by'))
                    ->placeholder(__('infrastructure.automated'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label(__('infrastructure.checked_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('infrastructure.status'))
                    ->options([
                        'healthy' => __('infrastructure.healthy'),
                        'warning' => __('infrastructure.warning'),
                        'critical' => __('infrastructure.critical'),
                    ]),
                Tables\Filters\SelectFilter::make('service')
                    ->label(__('infrastructure.service'))
                    ->options(fn () => SystemHealthCheck::query()->select('service')->distinct()->pluck('service', 'service')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('recheck')
                    ->label(__('infrastructure.recheck'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('infrastructure.manage'))
                    ->action(function (SystemHealthCheck $record) {
                        $start = microtime(true);
                        try {
                            $result = match ($record->service) {
                                'database' => static::recheckDatabase(),
                                'cache' => static::recheckCache(),
                                'queue' => static::recheckQueue(),
                                'storage' => static::recheckStorage(),
                                default => ['status' => 'healthy', 'details' => null, 'error' => null],
                            };
                            $responseTime = (int) ((microtime(true) - $start) * 1000);

                            SystemHealthCheck::create([
                                'service' => $record->service,
                                'status' => $result['status'],
                                'response_time_ms' => $responseTime,
                                'details' => $result['details'] ?? null,
                                'error_message' => $result['error'] ?? null,
                                'triggered_by' => auth('admin')->id(),
                                'checked_at' => now(),
                            ]);

                            Notification::make()
                                ->title(__('infrastructure.recheck_success', ['service' => $record->service]))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            $responseTime = (int) ((microtime(true) - $start) * 1000);
                            SystemHealthCheck::create([
                                'service' => $record->service,
                                'status' => 'critical',
                                'response_time_ms' => $responseTime,
                                'error_message' => $e->getMessage(),
                                'triggered_by' => auth('admin')->id(),
                                'checked_at' => now(),
                            ]);

                            Notification::make()
                                ->title(__('infrastructure.recheck_failed', ['service' => $record->service]))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('checked_at', 'desc')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => SystemHealthCheckResource\Pages\ListSystemHealthChecks::route('/'),
            'view' => SystemHealthCheckResource\Pages\ViewSystemHealthCheck::route('/{record}'),
        ];
    }

    private static function recheckDatabase(): array
    {
        DB::select('SELECT 1');
        $size = DB::select("SELECT pg_database_size(current_database()) as size")[0]->size ?? 0;

        return ['status' => 'healthy', 'details' => ['db_size_mb' => round($size / 1048576, 2)]];
    }

    private static function recheckCache(): array
    {
        $key = 'health_recheck_' . uniqid();
        Cache::put($key, 'ok', 10);
        $value = Cache::get($key);
        Cache::forget($key);

        return [
            'status' => $value === 'ok' ? 'healthy' : 'critical',
            'details' => ['driver' => config('cache.default')],
            'error' => $value !== 'ok' ? 'Cache read/write failed' : null,
        ];
    }

    private static function recheckQueue(): array
    {
        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedRecent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

        $status = 'healthy';
        if ($failedRecent > 10 || $pendingJobs > 1000) {
            $status = 'critical';
        } elseif ($failedRecent > 0 || $pendingJobs > 100) {
            $status = 'warning';
        }

        return ['status' => $status, 'details' => ['pending_jobs' => $pendingJobs, 'failed_last_hour' => $failedRecent]];
    }

    private static function recheckStorage(): array
    {
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);
        $usedPercent = $totalBytes > 0 ? round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1) : 0;

        $status = 'healthy';
        if ($usedPercent > 95) {
            $status = 'critical';
        } elseif ($usedPercent > 85) {
            $status = 'warning';
        }

        return ['status' => $status, 'details' => ['free_gb' => round($freeBytes / 1073741824, 2), 'used_percent' => $usedPercent]];
    }
}
