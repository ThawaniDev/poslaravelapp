<?php

namespace App\Filament\Pages;

use App\Domain\DeliveryIntegration\Models\DeliveryMenuSyncLog;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Models\DeliveryStatusPushLog;
use App\Domain\DeliveryIntegration\Models\DeliveryWebhookLog;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sync Health Dashboard — platform-wide sync monitoring page.
 * Route: /admin/integrations/health
 */
class DeliverySyncHealthDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_integrations');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('delivery.sync_health_dashboard');
    }

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.delivery-sync-health-dashboard';

    protected static ?string $pollingInterval = '60s';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['integrations.view', 'integrations.manage']);
    }

    public function getTitle(): string
    {
        return __('delivery.sync_health_dashboard');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('delivery.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => Notification::make()->title(__('delivery.dashboard_refreshed'))->success()->send()),
        ];
    }

    // ─── Stats ────────────────────────────────────────────────────────────

    public function getStats(): array
    {
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        $totalIntegrations  = DeliveryPlatformConfig::where('is_enabled', true)->count();
        $errorIntegrations  = DeliveryPlatformConfig::where('status', 'error')->count();
        $recentSyncTotal    = DeliveryMenuSyncLog::where('started_at', '>=', $twentyFourHoursAgo)->count();
        $recentSyncFailed   = DeliveryMenuSyncLog::where('started_at', '>=', $twentyFourHoursAgo)
            ->where('status', 'failed')->count();
        $errorRate          = $recentSyncTotal > 0
            ? round(($recentSyncFailed / $recentSyncTotal) * 100, 1)
            : 0;

        $pendingOrders      = DeliveryOrderMapping::where('delivery_status', 'pending')->count();
        $pushFailures24h    = DeliveryStatusPushLog::where('pushed_at', '>=', $twentyFourHoursAgo)
            ->where('success', false)->count();
        $webhookFailures24h = DeliveryWebhookLog::where('received_at', '>=', $twentyFourHoursAgo)
            ->where('signature_valid', false)->count();

        return [
            'total_integrations'  => $totalIntegrations,
            'error_integrations'  => $errorIntegrations,
            'error_rate'          => $errorRate,
            'recent_sync_total'   => $recentSyncTotal,
            'recent_sync_failed'  => $recentSyncFailed,
            'pending_orders'      => $pendingOrders,
            'push_failures_24h'   => $pushFailures24h,
            'webhook_failures_24h' => $webhookFailures24h,
        ];
    }

    /** Per-platform stats for bar chart / table. */
    public function getPlatformBreakdown(): array
    {
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        return DeliveryPlatform::where('is_active', true)
            ->get()
            ->map(function (DeliveryPlatform $platform) use ($twentyFourHoursAgo) {
                $total  = DeliveryMenuSyncLog::where('platform', $platform->slug)
                    ->where('started_at', '>=', $twentyFourHoursAgo)->count();
                $failed = DeliveryMenuSyncLog::where('platform', $platform->slug)
                    ->where('started_at', '>=', $twentyFourHoursAgo)
                    ->where('status', 'failed')->count();

                return [
                    'name'         => $platform->name,
                    'slug'         => $platform->slug,
                    'total_syncs'  => $total,
                    'failed_syncs' => $failed,
                    'error_rate'   => $total > 0 ? round($failed / $total * 100, 1) : 0,
                    'configs'      => DeliveryPlatformConfig::where('platform', $platform->slug)
                        ->where('is_enabled', true)->count(),
                ];
            })
            ->toArray();
    }

    /** Top 5 error messages from sync logs in last 24h. */
    public function getTopErrors(): array
    {
        $twentyFourHoursAgo = Carbon::now()->subHours(24);

        return DeliveryMenuSyncLog::where('started_at', '>=', $twentyFourHoursAgo)
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->select('error_message', 'platform')
            ->limit(50)
            ->get()
            ->groupBy('error_message')
            ->map(fn ($group) => [
                'message'  => $group->first()->error_message,
                'platform' => $group->first()->platform,
                'count'    => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();
    }

    // ─── Table: Failed Syncs (last 50) ────────────────────────────────────

    protected function getTableQuery(): Builder
    {
        return DeliveryMenuSyncLog::where('status', 'failed')
            ->orderByDesc('started_at')
            ->limit(50);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->label(__('delivery.platform'))
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('store_id')
                    ->label(__('delivery.store_id'))
                    ->limit(12)
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('items_synced')
                    ->label(__('delivery.items_synced'))
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('items_failed')
                    ->label(__('delivery.items_failed'))
                    ->numeric()
                    ->color('danger')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('delivery.error_message'))
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label(__('delivery.triggered_by'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('delivery.started_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label(__('delivery.retry_sync'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function ($record) {
                        \App\Domain\DeliveryIntegration\Jobs\MenuSyncJob::dispatch($record->store_id . '|' . $record->platform, []);
                        Notification::make()->title(__('delivery.retry_queued'))->success()->send();
                    })
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('integrations.manage')),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
