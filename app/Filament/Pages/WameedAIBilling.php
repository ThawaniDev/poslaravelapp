<?php

namespace App\Filament\Pages;

use App\Domain\WameedAI\Models\AIBillingInvoice;
use App\Domain\WameedAI\Models\AIBillingSetting;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WameedAIBilling extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.wameed-ai-billing';

    public string $activeTab = 'overview';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_billing');
    }

    public function getTitle(): string
    {
        return 'AI Billing';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.view', 'wameed_ai.manage']);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getViewData(): array
    {
        $now = now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        // Overview stats
        $totalInvoices = AIBillingInvoice::count();
        $pendingInvoices = AIBillingInvoice::where('status', 'pending')->count();
        $paidInvoices = AIBillingInvoice::where('status', 'paid')->count();
        $overdueInvoices = AIBillingInvoice::where('status', 'overdue')->count();

        $totalRevenue = AIBillingInvoice::where('status', 'paid')->sum('billed_amount_usd');
        $totalRawCost = AIBillingInvoice::where('status', 'paid')->sum('raw_cost_usd');
        $totalMargin = AIBillingInvoice::where('status', 'paid')->sum('margin_amount_usd');
        $pendingRevenue = AIBillingInvoice::where('status', 'pending')->sum('billed_amount_usd');
        $currentMonthRevenue = AIBillingInvoice::where('year', $currentYear)
            ->where('month', $currentMonth)
            ->sum('billed_amount_usd');
        $currentMonthRawCost = AIBillingInvoice::where('year', $currentYear)
            ->where('month', $currentMonth)
            ->sum('raw_cost_usd');

        // Store configs
        $totalStores = AIStoreBillingConfig::count();
        $enabledStores = AIStoreBillingConfig::where('is_ai_enabled', true)->count();
        $disabledStores = AIStoreBillingConfig::where('is_ai_enabled', false)->count();

        // Recent invoices
        $recentInvoices = AIBillingInvoice::with('store:id,business_name')
            ->latest()
            ->limit(20)
            ->get();

        // Store configs list
        $storeConfigs = AIStoreBillingConfig::with('store:id,business_name')
            ->latest('updated_at')
            ->limit(20)
            ->get();

        // Billing settings
        $settings = AIBillingSetting::pluck('value', 'key')->toArray();

        return compact(
            'totalInvoices', 'pendingInvoices', 'paidInvoices', 'overdueInvoices',
            'totalRevenue', 'totalRawCost', 'totalMargin', 'pendingRevenue',
            'currentMonthRevenue', 'currentMonthRawCost',
            'totalStores', 'enabledStores', 'disabledStores',
            'recentInvoices', 'storeConfigs', 'settings',
        );
    }
}
