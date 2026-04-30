<?php

namespace App\Filament\Pages;

use App\Domain\WameedAI\Models\AIBillingInvoice;
use App\Domain\WameedAI\Models\AIBillingSetting;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WameedAIBilling extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.wameed-ai-billing';

    public string $activeTab = 'overview';

    // Settings edit state
    public array $editingSettings = [];

    // Store config edit state
    public ?string $editingConfigId = null;
    public bool $editConfigAiEnabled = true;
    public ?string $editConfigMonthlyLimit = null;
    public ?string $editConfigCustomMargin = null;
    public ?string $editConfigNotes = null;

    // Invoice action
    public ?string $markingInvoiceId = null;
    public string $paymentReference = '';
    public string $paymentNotes = '';

    // New setting form state
    public string $newSettingKey = '';
    public string $newSettingValue = '';
    public string $newSettingDesc = '';

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
        return __('ai.ai_billing_title');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.view', 'wameed_ai.manage']);
    }

    protected function hasManagePermission(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.manage']);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->editingConfigId = null;
        $this->markingInvoiceId = null;
    }

    // --- Settings Actions ---

    public function saveSetting(string $key): void
    {
        if (! $this->hasManagePermission()) {
            return;
        }
        $value = $this->editingSettings[$key] ?? '';
        AIBillingSetting::setValue($key, $value);
        Notification::make()->title(__('ai.notif_setting_updated'))->success()->send();
    }

    public function addNewSetting(): void
    {
        if (! $this->hasManagePermission()) {
            return;
        }
        if (empty($this->newSettingKey)) {
            return;
        }
        AIBillingSetting::setValue($this->newSettingKey, $this->newSettingValue, $this->newSettingDesc ?: null);
        $this->newSettingKey = '';
        $this->newSettingValue = '';
        $this->newSettingDesc = '';
        $this->editingSettings = AIBillingSetting::pluck('value', 'key')->toArray();
        Notification::make()->title(__('ai.notif_setting_added'))->success()->send();
    }

    public function deleteSetting(string $key): void
    {
        if (! $this->hasManagePermission()) {
            return;
        }
        AIBillingSetting::where('key', $key)->delete();
        unset($this->editingSettings[$key]);
        Notification::make()->title(__('ai.notif_setting_deleted'))->success()->send();
    }

    // --- Store Config Actions ---

    public function editStoreConfig(string $configId): void
    {
        $config = AIStoreBillingConfig::find($configId);
        if (! $config) {
            return;
        }
        $this->editingConfigId = $configId;
        $this->editConfigAiEnabled = (bool) $config->is_ai_enabled;
        $this->editConfigMonthlyLimit = $config->monthly_limit_usd > 0 ? (string) $config->monthly_limit_usd : '';
        $this->editConfigCustomMargin = $config->custom_margin_percentage !== null ? (string) $config->custom_margin_percentage : '';
        $this->editConfigNotes = $config->notes ?? '';
    }

    public function saveStoreConfig(): void
    {
        if (! $this->hasManagePermission() || ! $this->editingConfigId) {
            return;
        }
        $config = AIStoreBillingConfig::find($this->editingConfigId);
        if (! $config) {
            return;
        }

        $config->update([
            'is_ai_enabled' => $this->editConfigAiEnabled,
            'monthly_limit_usd' => $this->editConfigMonthlyLimit !== '' ? (float) $this->editConfigMonthlyLimit : 0,
            'custom_margin_percentage' => $this->editConfigCustomMargin !== '' ? (float) $this->editConfigCustomMargin : null,
            'notes' => $this->editConfigNotes ?: null,
            'enabled_at' => $this->editConfigAiEnabled ? now() : $config->enabled_at,
            'disabled_at' => ! $this->editConfigAiEnabled ? now() : $config->disabled_at,
        ]);

        $this->editingConfigId = null;
        Notification::make()->title(__('ai.notif_store_config_updated'))->success()->send();
    }

    public function cancelEditConfig(): void
    {
        $this->editingConfigId = null;
    }

    public function toggleStoreAI(string $configId): void
    {
        if (! $this->hasManagePermission()) {
            return;
        }
        $config = AIStoreBillingConfig::find($configId);
        if (! $config) {
            return;
        }
        $newState = ! $config->is_ai_enabled;
        $config->update([
            'is_ai_enabled' => $newState,
            'enabled_at' => $newState ? now() : $config->enabled_at,
            'disabled_at' => ! $newState ? now() : $config->disabled_at,
        ]);
        Notification::make()
            ->title($newState ? __('ai.notif_ai_enabled') : __('ai.notif_ai_disabled'))
            ->body($newState ? __('ai.ai_enabled_body') : __('ai.ai_disabled_body'))
            ->success()
            ->send();
    }

    // --- Invoice Actions ---

    public function startMarkPaid(string $invoiceId): void
    {
        $this->markingInvoiceId = $invoiceId;
        $this->paymentReference = '';
        $this->paymentNotes = '';
    }

    public function cancelMarkPaid(): void
    {
        $this->markingInvoiceId = null;
    }

    public function markInvoicePaid(): void
    {
        if (! $this->hasManagePermission() || ! $this->markingInvoiceId) {
            return;
        }
        $invoice = AIBillingInvoice::find($this->markingInvoiceId);
        if (! $invoice) {
            return;
        }
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $this->paymentReference ?: null,
            'payment_notes' => $this->paymentNotes ?: null,
        ]);
        $this->markingInvoiceId = null;
        Notification::make()->title(__('ai.notif_invoice_paid'))->success()->send();
    }

    public function markInvoiceOverdue(string $invoiceId): void
    {
        if (! $this->hasManagePermission()) {
            return;
        }
        $invoice = AIBillingInvoice::find($invoiceId);
        if (! $invoice || $invoice->status !== 'pending') {
            return;
        }
        $invoice->update(['status' => 'overdue']);
        Notification::make()->title(__('ai.notif_invoice_overdue'))->warning()->send();
    }

    // --- Generate Invoices ---

    public function generateInvoices(): void
    {
        if (! $this->hasManagePermission()) {
            return;
        }

        $lastMonth = now()->subMonth();
        $year = $lastMonth->year;
        $month = $lastMonth->month;
        $periodStart = $lastMonth->startOfMonth()->toDateString();
        $periodEnd = $lastMonth->endOfMonth()->toDateString();

        // Get stores with usage but no invoice for that period
        $storesWithUsage = AIUsageLog::where('status', 'success')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->select('store_id')
            ->selectRaw('count(*) as total_requests')
            ->selectRaw('sum(total_tokens) as total_tokens')
            ->selectRaw('sum(estimated_cost_usd) as raw_cost')
            ->selectRaw('sum(billed_cost_usd) as billed_cost')
            ->groupBy('store_id')
            ->get();

        $created = 0;
        foreach ($storesWithUsage as $usage) {
            $exists = AIBillingInvoice::where('store_id', $usage->store_id)
                ->where('year', $year)
                ->where('month', $month)
                ->exists();

            if ($exists) {
                continue;
            }

            $rawCost = (float) $usage->raw_cost;
            $billedCost = (float) $usage->billed_cost;
            $marginAmount = $billedCost - $rawCost;
            $marginPct = $rawCost > 0 ? ($marginAmount / $rawCost) * 100 : 0;

            $store = \App\Domain\Core\Models\Store::find($usage->store_id);
            $orgId = $store?->organization_id;

            $invoiceNumber = 'AI-' . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . strtoupper(substr($usage->store_id, 0, 8));

            AIBillingInvoice::create([
                'store_id' => $usage->store_id,
                'organization_id' => $orgId,
                'invoice_number' => $invoiceNumber,
                'year' => $year,
                'month' => $month,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_requests' => $usage->total_requests,
                'total_tokens' => $usage->total_tokens,
                'raw_cost_usd' => $rawCost,
                'margin_percentage' => $marginPct,
                'margin_amount_usd' => $marginAmount,
                'billed_amount_usd' => $billedCost,
                'status' => 'pending',
                'due_date' => now()->addDays(30),
                'generated_at' => now(),
            ]);
            $created++;
        }

        Notification::make()
            ->title(__('ai.notif_invoices_generated') . ": {$created}")
            ->body(__('ai.billing_month_subtitle', ['year' => $year, 'month' => str_pad($month, 2, '0', STR_PAD_LEFT)]))
            ->success()
            ->send();
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
        $recentInvoices = AIBillingInvoice::with('store:id,name')
            ->latest()
            ->limit(50)
            ->get();

        // Ensure every active store has a billing config row so admins can
        // configure stores even before they trigger any AI usage.
        $existingStoreIds = AIStoreBillingConfig::whereNotNull('store_id')->pluck('store_id')->all();
        $missingStores = \App\Domain\Core\Models\Store::where('is_active', true)
            ->whereNotIn('id', $existingStoreIds)
            ->get(['id', 'organization_id']);
        foreach ($missingStores as $store) {
            AIStoreBillingConfig::create([
                'store_id' => $store->id,
                'organization_id' => $store->organization_id,
                'is_ai_enabled' => true,
                'monthly_limit_usd' => 0,
                'enabled_at' => now(),
            ]);
        }

        // Store configs list (now includes the just-created defaults).
        $storeConfigs = AIStoreBillingConfig::with(['store:id,name', 'organization:id,name'])
            ->orderByRaw('CASE WHEN store_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Billing settings with descriptions
        $settingsRaw = AIBillingSetting::all();
        $settings = $settingsRaw->pluck('value', 'key')->toArray();
        $settingDescriptions = $settingsRaw->pluck('description', 'key')->toArray();

        // Init editing settings state
        if (empty($this->editingSettings)) {
            $this->editingSettings = $settings;
        }

        $canManage = $this->hasManagePermission();

        return compact(
            'totalInvoices', 'pendingInvoices', 'paidInvoices', 'overdueInvoices',
            'totalRevenue', 'totalRawCost', 'totalMargin', 'pendingRevenue',
            'currentMonthRevenue', 'currentMonthRawCost',
            'totalStores', 'enabledStores', 'disabledStores',
            'recentInvoices', 'storeConfigs', 'settings', 'settingDescriptions', 'canManage',
        );
    }
}
