<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\SystemHealthCheck;
use App\Domain\BackupSync\Models\DatabaseBackup;
use App\Domain\Core\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class InfrastructureDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_infrastructure');
    }

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.infrastructure-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('infrastructure.dashboard');
    }

    public function getTitle(): string
    {
        return __('infrastructure.infrastructure_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['infrastructure.view', 'infrastructure.manage']);
    }

    public function getViewData(): array
    {
        return [
            'failedJobsCount' => FailedJob::count(),
            'failedJobsLast24h' => FailedJob::where('failed_at', '>=', now()->subDay())->count(),
            'backupsCompleted' => DatabaseBackup::where('status', 'completed')->count(),
            'backupsFailed' => DatabaseBackup::where('status', 'failed')->count(),
            'lastBackup' => DatabaseBackup::where('status', 'completed')->latest('completed_at')->first(),
            'healthyServices' => SystemHealthCheck::where('status', 'healthy')->count(),
            'warningServices' => SystemHealthCheck::where('status', 'warning')->count(),
            'criticalServices' => SystemHealthCheck::where('status', 'critical')->count(),
            'latestHealthChecks' => SystemHealthCheck::latest('checked_at')->take(10)->get(),
            'recentFailedJobs' => FailedJob::latest('failed_at')->take(5)->get(),
            'serverInfo' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_usage' => number_format(memory_get_usage(true) / 1048576, 2) . ' MB',
                'memory_peak' => number_format(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
                'db_connection' => config('database.default'),
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flush_cache')
                ->label(__('infrastructure.flush_cache'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    Cache::flush();
                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'flush_cache',
                        entityType: 'infrastructure',
                        entityId: 'cache',
                        details: [],
                    );
                    Notification::make()
                        ->title(__('infrastructure.cache_flushed'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
