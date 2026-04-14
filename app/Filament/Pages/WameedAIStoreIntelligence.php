<?php

namespace App\Filament\Pages;

use App\Domain\Core\Models\Store;
use App\Domain\WameedAI\Models\AIBillingInvoice;
use App\Domain\WameedAI\Models\AIBillingInvoiceItem;
use App\Domain\WameedAI\Models\AIBillingPayment;
use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use App\Domain\WameedAI\Models\AIDailyUsageSummary;
use App\Domain\WameedAI\Models\AIMonthlyUsageSummary;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use App\Domain\WameedAI\Models\AIStoreFeatureConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WameedAIStoreIntelligence extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.wameed-ai-store-intelligence';

    public string $activeTab = 'overview';

    public ?string $selectedStoreId = null;

    public string $searchQuery = '';

    // Chat viewer
    public ?string $selectedChatId = null;

    // Log viewer
    public int $logPage = 1;

    public int $logsPerPage = 25;

    // Date filters
    public string $dateRange = '30'; // days

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_store_intelligence');
    }

    public function getTitle(): string
    {
        return __('nav.ai_store_intelligence');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['wameed_ai.view', 'wameed_ai.manage']);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->selectedChatId = null;
        $this->logPage = 1;
    }

    public function selectStore(string $storeId): void
    {
        $this->selectedStoreId = $storeId;
        $this->activeTab = 'overview';
        $this->selectedChatId = null;
        $this->logPage = 1;
    }

    public function clearStore(): void
    {
        $this->selectedStoreId = null;
        $this->activeTab = 'overview';
        $this->selectedChatId = null;
    }

    public function selectChat(string $chatId): void
    {
        $this->selectedChatId = $chatId;
    }

    public function clearChat(): void
    {
        $this->selectedChatId = null;
    }

    public function nextLogPage(): void
    {
        $this->logPage++;
    }

    public function prevLogPage(): void
    {
        if ($this->logPage > 1) {
            $this->logPage--;
        }
    }

    // ─── Store List Data ────────────────────────────────────────

    protected function getStoreListData(): array
    {
        $days = (int) $this->dateRange;

        // All stores with AI usage data, using subqueries for efficiency
        $storesQuery = Store::query()
            ->select('stores.id', 'stores.name', 'stores.name_ar', 'stores.slug', 'stores.is_active', 'stores.business_type', 'stores.created_at')
            ->addSelect([
                'total_requests' => AIUsageLog::selectRaw('COUNT(*)')
                    ->whereColumn('store_id', 'stores.id'),
                'total_raw_cost' => AIUsageLog::selectRaw('COALESCE(SUM(estimated_cost_usd), 0)')
                    ->whereColumn('store_id', 'stores.id'),
                'total_billed_cost' => AIUsageLog::selectRaw('COALESCE(SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END), 0)')
                    ->whereColumn('store_id', 'stores.id'),
                'recent_requests' => AIUsageLog::selectRaw('COUNT(*)')
                    ->whereColumn('store_id', 'stores.id')
                    ->where('ai_usage_logs.created_at', '>=', now()->subDays($days)),
                'recent_raw_cost' => AIUsageLog::selectRaw('COALESCE(SUM(estimated_cost_usd), 0)')
                    ->whereColumn('store_id', 'stores.id')
                    ->where('ai_usage_logs.created_at', '>=', now()->subDays($days)),
                'recent_billed_cost' => AIUsageLog::selectRaw('COALESCE(SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END), 0)')
                    ->whereColumn('store_id', 'stores.id')
                    ->where('ai_usage_logs.created_at', '>=', now()->subDays($days)),
                'total_chats' => AIChat::selectRaw('COUNT(*)')
                    ->whereColumn('store_id', 'stores.id'),
                'last_ai_activity' => AIUsageLog::selectRaw('MAX(created_at)')
                    ->whereColumn('store_id', 'stores.id'),
                'total_tokens_used' => AIUsageLog::selectRaw('COALESCE(SUM(total_tokens), 0)')
                    ->whereColumn('store_id', 'stores.id'),
                'error_count' => AIUsageLog::selectRaw('COUNT(*)')
                    ->whereColumn('store_id', 'stores.id')
                    ->where('status', 'error'),
            ]);

        // Only show stores that have at least 1 AI usage log or an AI billing config
        $storesQuery->where(function ($q) {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('ai_usage_logs')
                    ->whereColumn('ai_usage_logs.store_id', 'stores.id');
            })
            ->orWhereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('ai_store_billing_configs')
                    ->whereColumn('ai_store_billing_configs.store_id', 'stores.id');
            });
        });

        if ($this->searchQuery) {
            $search = '%' . $this->searchQuery . '%';
            $storesQuery->where(function ($q) use ($search) {
                $q->where('stores.name', 'ilike', $search)
                    ->orWhere('stores.name_ar', 'ilike', $search)
                    ->orWhere('stores.slug', 'ilike', $search);
            });
        }

        $stores = $storesQuery->orderByDesc('recent_requests')->get();

        // Platform totals
        $platformTotals = [
            'total_stores' => $stores->count(),
            'total_requests' => $stores->sum('total_requests'),
            'total_raw_cost' => $stores->sum('total_raw_cost'),
            'total_billed_cost' => $stores->sum('total_billed_cost'),
            'total_margin' => $stores->sum('total_billed_cost') - $stores->sum('total_raw_cost'),
            'recent_requests' => $stores->sum('recent_requests'),
            'recent_raw_cost' => $stores->sum('recent_raw_cost'),
            'recent_billed_cost' => $stores->sum('recent_billed_cost'),
            'total_chats' => $stores->sum('total_chats'),
            'total_tokens' => $stores->sum('total_tokens_used'),
            'total_errors' => $stores->sum('error_count'),
        ];

        return [
            'stores' => $stores,
            'platformTotals' => $platformTotals,
        ];
    }

    // ─── Store Detail Data ──────────────────────────────────────

    protected function getStoreDetailData(): array
    {
        if (! $this->selectedStoreId) {
            return [];
        }

        $storeId = $this->selectedStoreId;
        $store = Store::find($storeId);
        if (! $store) {
            return [];
        }

        $days = (int) $this->dateRange;

        // ── Billing config
        $billingConfig = AIStoreBillingConfig::where('store_id', $storeId)->first();

        // ── Overview stats
        $totalRequests = AIUsageLog::where('store_id', $storeId)->count();
        $totalRawCost = (float) AIUsageLog::where('store_id', $storeId)->sum('estimated_cost_usd');
        $totalBilledCost = (float) AIUsageLog::where('store_id', $storeId)->sum(DB::raw('CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END'));
        $totalTokens = (int) AIUsageLog::where('store_id', $storeId)->sum('total_tokens');
        $avgLatency = round((float) AIUsageLog::where('store_id', $storeId)->avg('latency_ms'));

        $recentTotal = AIUsageLog::where('store_id', $storeId)->where('created_at', '>=', now()->subDays($days))->count();
        $recentRawCost = (float) AIUsageLog::where('store_id', $storeId)->where('created_at', '>=', now()->subDays($days))->sum('estimated_cost_usd');
        $recentBilledCost = (float) AIUsageLog::where('store_id', $storeId)->where('created_at', '>=', now()->subDays($days))->sum(DB::raw('CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END'));

        $todayRequests = AIUsageLog::where('store_id', $storeId)->whereDate('created_at', today())->count();
        $todayRawCost = (float) AIUsageLog::where('store_id', $storeId)->whereDate('created_at', today())->sum('estimated_cost_usd');
        $todayBilledCost = (float) AIUsageLog::where('store_id', $storeId)->whereDate('created_at', today())->sum(DB::raw('CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END'));

        $cacheHitRate = $totalRequests > 0
            ? round(AIUsageLog::where('store_id', $storeId)->where('response_cached', true)->count() / $totalRequests * 100, 1)
            : 0;
        $errorCount = AIUsageLog::where('store_id', $storeId)->where('status', 'error')->count();
        $errorRate = $totalRequests > 0 ? round($errorCount / $totalRequests * 100, 1) : 0;

        $lastActivity = AIUsageLog::where('store_id', $storeId)->latest('created_at')->value('created_at');
        $firstActivity = AIUsageLog::where('store_id', $storeId)->oldest('created_at')->value('created_at');

        $totalChats = AIChat::where('store_id', $storeId)->count();
        $totalChatMessages = AIChatMessage::whereIn('chat_id', AIChat::where('store_id', $storeId)->pluck('id'))->count();

        $overview = [
            'store' => $store,
            'billingConfig' => $billingConfig,
            'totalRequests' => $totalRequests,
            'totalRawCost' => $totalRawCost,
            'totalBilledCost' => $totalBilledCost,
            'totalMargin' => $totalBilledCost - $totalRawCost,
            'totalTokens' => $totalTokens,
            'avgLatency' => $avgLatency,
            'recentRequests' => $recentTotal,
            'recentRawCost' => $recentRawCost,
            'recentBilledCost' => $recentBilledCost,
            'todayRequests' => $todayRequests,
            'todayRawCost' => $todayRawCost,
            'todayBilledCost' => $todayBilledCost,
            'cacheHitRate' => $cacheHitRate,
            'errorCount' => $errorCount,
            'errorRate' => $errorRate,
            'lastActivity' => $lastActivity,
            'firstActivity' => $firstActivity,
            'totalChats' => $totalChats,
            'totalChatMessages' => $totalChatMessages,
        ];

        return $overview;
    }

    // ─── Feature Breakdown ──────────────────────────────────────

    protected function getFeatureBreakdownData(): array
    {
        if (! $this->selectedStoreId) {
            return [];
        }

        $storeId = $this->selectedStoreId;
        $days = (int) $this->dateRange;

        // Feature usage breakdown
        $featureUsage = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                'feature_slug',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as raw_cost'),
                DB::raw('SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END) as billed_cost'),
                DB::raw('AVG(latency_ms) as avg_latency'),
                DB::raw('SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) as error_count'),
                DB::raw('SUM(CASE WHEN response_cached = true THEN 1 ELSE 0 END) as cached_count'),
                DB::raw('MAX(created_at) as last_used'),
            )
            ->groupBy('feature_slug')
            ->orderByDesc('request_count')
            ->get();

        // All-time feature usage
        $allTimeFeatureUsage = AIUsageLog::where('store_id', $storeId)
            ->select(
                'feature_slug',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as raw_cost'),
                DB::raw('SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END) as billed_cost'),
            )
            ->groupBy('feature_slug')
            ->orderByDesc('request_count')
            ->get();

        // Store feature configs
        $featureConfigs = AIStoreFeatureConfig::where('store_id', $storeId)
            ->with('featureDefinition')
            ->get();

        // Models used
        $modelsUsed = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                'model_used',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as raw_cost'),
            )
            ->groupBy('model_used')
            ->orderByDesc('request_count')
            ->get();

        return [
            'featureUsage' => $featureUsage,
            'allTimeFeatureUsage' => $allTimeFeatureUsage,
            'featureConfigs' => $featureConfigs,
            'modelsUsed' => $modelsUsed,
        ];
    }

    // ─── Billing Data ───────────────────────────────────────────

    protected function getBillingData(): array
    {
        if (! $this->selectedStoreId) {
            return [];
        }

        $storeId = $this->selectedStoreId;

        // Billing config
        $billingConfig = AIStoreBillingConfig::where('store_id', $storeId)->first();

        // Invoices
        $invoices = AIBillingInvoice::where('store_id', $storeId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        // Invoice items for latest invoice
        $latestInvoice = $invoices->first();
        $latestInvoiceItems = $latestInvoice
            ? AIBillingInvoiceItem::where('ai_billing_invoice_id', $latestInvoice->id)->get()
            : collect();

        // All payments
        $payments = AIBillingPayment::whereIn('ai_billing_invoice_id', $invoices->pluck('id'))
            ->orderByDesc('created_at')
            ->get();

        // Monthly cost trend (from monthly summaries)
        $monthlySummaries = AIMonthlyUsageSummary::where('store_id', $storeId)
            ->orderByDesc('month')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        // Current month running cost
        $currentMonthRawCost = (float) AIUsageLog::where('store_id', $storeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('estimated_cost_usd');
        $currentMonthBilledCost = (float) AIUsageLog::where('store_id', $storeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum(DB::raw('CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END'));
        $currentMonthRequests = AIUsageLog::where('store_id', $storeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // Billing summary totals
        $totalInvoiced = $invoices->sum('billed_amount_usd');
        $totalPaid = $invoices->where('status', 'paid')->sum('billed_amount_usd');
        $totalPending = $invoices->where('status', 'pending')->sum('billed_amount_usd');
        $totalOverdue = $invoices->where('status', 'overdue')->sum('billed_amount_usd');

        return [
            'billingConfig' => $billingConfig,
            'invoices' => $invoices,
            'latestInvoice' => $latestInvoice,
            'latestInvoiceItems' => $latestInvoiceItems,
            'payments' => $payments,
            'monthlySummaries' => $monthlySummaries,
            'currentMonthRawCost' => $currentMonthRawCost,
            'currentMonthBilledCost' => $currentMonthBilledCost,
            'currentMonthRequests' => $currentMonthRequests,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'totalPending' => $totalPending,
            'totalOverdue' => $totalOverdue,
        ];
    }

    // ─── Daily Trends ───────────────────────────────────────────

    protected function getDailyTrends(): array
    {
        if (! $this->selectedStoreId) {
            return [];
        }

        $storeId = $this->selectedStoreId;
        $days = (int) $this->dateRange;

        // Daily usage from actual logs
        $dailyUsage = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(estimated_cost_usd) as raw_cost'),
                DB::raw('SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END) as billed_cost'),
                DB::raw('AVG(latency_ms) as avg_latency'),
                DB::raw('SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) as errors'),
                DB::raw('SUM(CASE WHEN response_cached = true THEN 1 ELSE 0 END) as cached'),
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Hourly breakdown for today
        $hourlyToday = AIUsageLog::where('store_id', $storeId)
            ->whereDate('created_at', today())
            ->select(
                DB::raw('EXTRACT(HOUR FROM created_at)::integer as hour'),
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(estimated_cost_usd) as raw_cost'),
            )
            ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)::integer'))
            ->orderBy('hour')
            ->get();

        // Daily summaries from aggregated table
        $dailySummaries = AIDailyUsageSummary::where('store_id', $storeId)
            ->orderByDesc('date')
            ->limit($days)
            ->get()
            ->reverse()
            ->values();

        return [
            'dailyUsage' => $dailyUsage,
            'hourlyToday' => $hourlyToday,
            'dailySummaries' => $dailySummaries,
        ];
    }

    // ─── Chat Data ──────────────────────────────────────────────

    protected function getChatData(): array
    {
        if (! $this->selectedStoreId) {
            return [];
        }

        $storeId = $this->selectedStoreId;

        $chats = AIChat::where('store_id', $storeId)
            ->with(['user:id,name'])
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $chatMessages = collect();
        $selectedChat = null;
        if ($this->selectedChatId) {
            $selectedChat = AIChat::with(['user:id,name'])->find($this->selectedChatId);
            if ($selectedChat) {
                $chatMessages = AIChatMessage::where('chat_id', $this->selectedChatId)
                    ->orderBy('created_at')
                    ->get();
            }
        }

        $chatStats = [
            'totalChats' => AIChat::where('store_id', $storeId)->count(),
            'totalMessages' => AIChatMessage::whereIn('chat_id', AIChat::where('store_id', $storeId)->pluck('id'))->count(),
            'totalTokens' => (int) AIChat::where('store_id', $storeId)->sum('total_tokens'),
            'totalCost' => (float) AIChat::where('store_id', $storeId)->sum('total_cost_usd'),
            'avgMessagesPerChat' => AIChat::where('store_id', $storeId)->count() > 0
                ? round(AIChatMessage::whereIn('chat_id', AIChat::where('store_id', $storeId)->pluck('id'))->count() / AIChat::where('store_id', $storeId)->count(), 1)
                : 0,
        ];

        return [
            'chats' => $chats,
            'chatMessages' => $chatMessages,
            'selectedChat' => $selectedChat,
            'chatStats' => $chatStats,
        ];
    }

    // ─── Recent Logs ────────────────────────────────────────────

    protected function getRecentLogs(): array
    {
        if (! $this->selectedStoreId) {
            return [];
        }

        $storeId = $this->selectedStoreId;
        $offset = ($this->logPage - 1) * $this->logsPerPage;

        $totalLogs = AIUsageLog::where('store_id', $storeId)->count();

        $logs = AIUsageLog::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($this->logsPerPage)
            ->get();

        $totalPages = max(1, ceil($totalLogs / $this->logsPerPage));

        return [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'totalPages' => $totalPages,
        ];
    }

    // ─── View Data ──────────────────────────────────────────────

    public function getViewData(): array
    {
        $storeList = $this->getStoreListData();

        $data = [
            'stores' => $storeList['stores'],
            'platformTotals' => $storeList['platformTotals'],
            'selectedStoreId' => $this->selectedStoreId,
            'dateRange' => $this->dateRange,
        ];

        if ($this->selectedStoreId) {
            $data['detail'] = $this->getStoreDetailData();

            if ($this->activeTab === 'features') {
                $data['features'] = $this->getFeatureBreakdownData();
            } elseif ($this->activeTab === 'billing') {
                $data['billing'] = $this->getBillingData();
            } elseif ($this->activeTab === 'trends') {
                $data['trends'] = $this->getDailyTrends();
            } elseif ($this->activeTab === 'chats') {
                $data['chatData'] = $this->getChatData();
            } elseif ($this->activeTab === 'logs') {
                $data['logData'] = $this->getRecentLogs();
            }
        }

        return $data;
    }
}
