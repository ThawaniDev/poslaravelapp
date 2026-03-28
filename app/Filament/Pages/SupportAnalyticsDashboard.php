<?php

namespace App\Filament\Pages;

use App\Domain\Support\Models\SupportTicket;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class SupportAnalyticsDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.support-analytics-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('analytics.support_analytics');
    }

    public function getTitle(): string
    {
        return __('analytics.support_analytics_dashboard');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'analytics.support']);
    }

    public function getViewData(): array
    {
        $openCount = SupportTicket::whereIn('status', ['open', 'pending', 'in_progress'])->count();
        $totalThisMonth = SupportTicket::where('created_at', '>=', now()->startOfMonth())->count();

        // Average first response time (hours) for resolved tickets
        $avgFirstResponse = SupportTicket::whereNotNull('first_response_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        // Average resolution time (hours)
        $avgResolution = SupportTicket::whereNotNull('resolved_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        // SLA compliance (tickets resolved before sla_deadline_at)
        $slaTotal = SupportTicket::whereNotNull('sla_deadline_at')
            ->whereNotNull('resolved_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $slaMet = SupportTicket::whereNotNull('sla_deadline_at')
            ->whereNotNull('resolved_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereColumn('resolved_at', '<=', 'sla_deadline_at')
            ->count();
        $slaRate = $slaTotal > 0 ? round(($slaMet / $slaTotal) * 100, 1) : 100;

        // Ticket volume trend (daily, last 30 days)
        $volumeTrend = SupportTicket::where('created_at', '>=', now()->subDays(30))
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count]);

        // Category breakdown
        $categoryBreakdown = SupportTicket::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category instanceof \BackedEnum ? $r->category->value : ($r->category ?? 'uncategorized'),
                'count' => (int) $r->count,
            ]);

        // Priority breakdown
        $priorityBreakdown = SupportTicket::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'priority' => $r->priority instanceof \BackedEnum ? $r->priority->value : ($r->priority ?? 'normal'),
                'count' => (int) $r->count,
            ]);

        return [
            'openCount' => $openCount,
            'totalThisMonth' => $totalThisMonth,
            'avgFirstResponse' => round((float) $avgFirstResponse, 1),
            'avgResolution' => round((float) $avgResolution, 1),
            'slaRate' => $slaRate,
            'volumeTrend' => $volumeTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
        ];
    }
}
