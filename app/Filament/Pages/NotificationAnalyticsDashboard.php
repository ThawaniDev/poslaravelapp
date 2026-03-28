<?php

namespace App\Filament\Pages;

use App\Domain\Notification\Models\NotificationDeliveryLog;
use App\Domain\Notification\Models\NotificationTemplate;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class NotificationAnalyticsDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.notification-analytics-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('analytics.notification_analytics');
    }

    public function getTitle(): string
    {
        return __('analytics.notification_analytics_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'analytics.notifications']);
    }

    public function getViewData(): array
    {
        $last30d = now()->subDays(30);

        // Overall stats
        $totalSent = NotificationDeliveryLog::where('created_at', '>=', $last30d)
            ->whereIn('status', ['sent', 'delivered', 'opened'])
            ->count();
        $totalDelivered = NotificationDeliveryLog::where('created_at', '>=', $last30d)
            ->whereIn('status', ['delivered', 'opened'])
            ->count();
        $totalOpened = NotificationDeliveryLog::where('created_at', '>=', $last30d)
            ->where('status', 'opened')
            ->count();
        $totalFailed = NotificationDeliveryLog::where('created_at', '>=', $last30d)
            ->where('status', 'failed')
            ->count();

        $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0;
        $openRate = $totalDelivered > 0 ? round(($totalOpened / $totalDelivered) * 100, 1) : 0;
        $failureRate = ($totalSent + $totalFailed) > 0
            ? round(($totalFailed / ($totalSent + $totalFailed)) * 100, 1) : 0;

        // Channel breakdown
        $channelBreakdown = NotificationDeliveryLog::where('created_at', '>=', $last30d)
            ->selectRaw("channel, status, COUNT(*) as count")
            ->groupBy('channel', 'status')
            ->get()
            ->groupBy(fn ($r) => $r->channel instanceof \BackedEnum ? $r->channel->value : $r->channel)
            ->map(function ($statuses, $channel) {
                $sent = $statuses->filter(fn ($s) => in_array(
                    $s->status instanceof \BackedEnum ? $s->status->value : $s->status,
                    ['sent', 'delivered', 'opened']
                ))->sum('count');
                $delivered = $statuses->filter(fn ($s) => in_array(
                    $s->status instanceof \BackedEnum ? $s->status->value : $s->status,
                    ['delivered', 'opened']
                ))->sum('count');
                $failed = $statuses->filter(fn ($s) =>
                    ($s->status instanceof \BackedEnum ? $s->status->value : $s->status) === 'failed'
                )->sum('count');
                return [
                    'channel' => $channel,
                    'sent' => $sent,
                    'delivered' => $delivered,
                    'failed' => $failed,
                ];
            })
            ->values();

        // Per-channel stats (top 15 by volume)
        // Note: notification_delivery_logs links to the standard Laravel notifications table
        // which does not have event_key. We join via notifications.type as the event identifier.
        $templateStats = NotificationDeliveryLog::where('notification_delivery_logs.created_at', '>=', $last30d)
            ->leftJoin('notifications', 'notification_delivery_logs.notification_id', '=', 'notifications.id')
            ->selectRaw("
                COALESCE(notifications.type, 'unknown') as event_key,
                notification_delivery_logs.channel,
                COUNT(*) as total,
                SUM(CASE WHEN notification_delivery_logs.status IN ('delivered','opened') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN notification_delivery_logs.status = 'opened' THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN notification_delivery_logs.status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->groupBy('notifications.type', 'notification_delivery_logs.channel')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'event_key' => class_basename($r->event_key),
                'channel' => $r->channel instanceof \BackedEnum ? $r->channel->value : $r->channel,
                'total' => (int) $r->total,
                'delivered' => (int) $r->delivered,
                'opened' => (int) $r->opened,
                'failed' => (int) $r->failed,
                'delivery_rate' => $r->total > 0 ? round(($r->delivered / $r->total) * 100, 1) : 0,
            ]);

        // Daily volume trend
        $dailyTrend = NotificationDeliveryLog::where('created_at', '>=', $last30d)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->date,
                'total' => (int) $r->total,
                'failed' => (int) $r->failed,
            ]);

        return [
            'totalSent' => $totalSent,
            'totalDelivered' => $totalDelivered,
            'totalOpened' => $totalOpened,
            'totalFailed' => $totalFailed,
            'deliveryRate' => $deliveryRate,
            'openRate' => $openRate,
            'failureRate' => $failureRate,
            'channelBreakdown' => $channelBreakdown,
            'templateStats' => $templateStats,
            'dailyTrend' => $dailyTrend,
        ];
    }
}
