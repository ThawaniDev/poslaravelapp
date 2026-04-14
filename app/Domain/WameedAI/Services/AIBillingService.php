<?php

namespace App\Domain\WameedAI\Services;

use App\Domain\WameedAI\Models\AIBillingInvoice;
use App\Domain\WameedAI\Models\AIBillingInvoiceItem;
use App\Domain\WameedAI\Models\AIBillingPayment;
use App\Domain\WameedAI\Models\AIBillingSetting;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AIMonthlyUsageSummary;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use App\Domain\WameedAI\Models\AIUsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIBillingService
{
    // ─── Billing Check (called before every AI request) ──────────

    /**
     * Check if a store is allowed to use AI (billing-wise).
     * Returns [allowed, reason] tuple.
     */
    public function canStoreUseAI(string $storeId, string $organizationId): array
    {
        if (!AIBillingSetting::getBool('billing_enabled', true)) {
            return [true, null];
        }

        $config = AIStoreBillingConfig::getOrCreateForStore($storeId, $organizationId);

        if (!$config->is_ai_enabled) {
            return [false, $config->disabled_reason ?? 'ai_disabled_by_admin'];
        }

        // Check monthly spending limit
        $currentMonthCost = $this->getCurrentMonthBilledCost($storeId);
        if (!$config->isWithinMonthlyLimit($currentMonthCost)) {
            return [false, 'monthly_limit_exceeded'];
        }

        return [true, null];
    }

    /**
     * Get current month's billed cost (raw cost + margin) for a store.
     */
    public function getCurrentMonthBilledCost(string $storeId): float
    {
        $monthStart = now()->startOfMonth();

        // Use pre-calculated billed_cost_usd from logs (margin applied at save time)
        $billedCost = (float) AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', $monthStart)
            ->where('status', 'success')
            ->sum(DB::raw('CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd END'));

        return round($billedCost, 5);
    }

    /**
     * Get current month's raw cost for a store.
     */
    public function getCurrentMonthRawCost(string $storeId): float
    {
        return (float) AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('status', 'success')
            ->sum('estimated_cost_usd');
    }

    /**
     * Get the effective margin percentage for a store.
     */
    public function getEffectiveMarginForStore(string $storeId): float
    {
        $config = AIStoreBillingConfig::where('store_id', $storeId)->first();
        if ($config && $config->custom_margin_percentage !== null) {
            return (float) $config->custom_margin_percentage;
        }
        return AIBillingSetting::getFloat('margin_percentage', 20.0);
    }

    // ─── Invoice Generation ──────────────────────────────────────

    /**
     * Generate monthly invoices for all stores that used AI in a given month.
     */
    public function generateMonthlyInvoices(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->subMonth()->year;
        $month = $month ?? now()->subMonth()->month;

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $minBillable = AIBillingSetting::getFloat('min_billable_amount_usd', 0.01);

        // Get all stores that had AI usage in this period
        $stores = AIUsageLog::where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $monthEnd)
            ->where('status', 'success')
            ->select('store_id', 'organization_id')
            ->distinct()
            ->get();

        $generated = [];
        $skipped = [];

        foreach ($stores as $storeRow) {
            try {
                $result = $this->generateInvoiceForStore(
                    $storeRow->store_id,
                    $storeRow->organization_id,
                    $year,
                    $month,
                    $monthStart,
                    $monthEnd,
                    $minBillable,
                );

                if ($result) {
                    $generated[] = $result;
                } else {
                    $skipped[] = $storeRow->store_id;
                }
            } catch (\Throwable $e) {
                Log::error("AI Billing: Failed to generate invoice for store {$storeRow->store_id}: {$e->getMessage()}");
                $skipped[] = $storeRow->store_id;
            }
        }

        return [
            'generated' => count($generated),
            'skipped' => count($skipped),
            'invoices' => $generated,
            'year' => $year,
            'month' => $month,
        ];
    }

    /**
     * Generate an invoice for a specific store.
     */
    public function generateInvoiceForStore(
        string $storeId,
        string $organizationId,
        int $year,
        int $month,
        Carbon $monthStart,
        Carbon $monthEnd,
        float $minBillable = 0.01,
    ): ?AIBillingInvoice {
        // Check if invoice already exists
        $existing = AIBillingInvoice::forStore($storeId)->forPeriod($year, $month)->first();
        if ($existing) {
            return null;
        }

        // Get usage breakdown by feature
        $featureBreakdown = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $monthEnd)
            ->where('status', 'success')
            ->groupBy('feature_slug')
            ->selectRaw("
                feature_slug,
                COUNT(*) as request_count,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as raw_cost
            ")
            ->get();

        $totalRequests = $featureBreakdown->sum('request_count');
        $totalTokens = $featureBreakdown->sum('total_tokens');
        $totalRawCost = (float) $featureBreakdown->sum('raw_cost');

        if ($totalRawCost <= 0) {
            return null;
        }

        $marginPercentage = $this->getEffectiveMarginForStore($storeId);
        $marginAmount = round($totalRawCost * ($marginPercentage / 100), 5);
        $billedAmount = round($totalRawCost + $marginAmount, 5);

        if ($billedAmount < $minBillable) {
            return null;
        }

        $graceDays = AIBillingSetting::getInt('auto_disable_grace_days', 5);
        $dueDate = Carbon::create($year, $month, 1)->addMonth()->addDays($graceDays);

        return DB::transaction(function () use (
            $storeId, $organizationId, $year, $month, $monthStart, $monthEnd,
            $totalRequests, $totalTokens, $totalRawCost, $marginPercentage,
            $marginAmount, $billedAmount, $dueDate, $featureBreakdown,
        ) {
            $invoice = AIBillingInvoice::create([
                'store_id' => $storeId,
                'organization_id' => $organizationId,
                'invoice_number' => AIBillingInvoice::generateInvoiceNumber($storeId, $year, $month),
                'year' => $year,
                'month' => $month,
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
                'total_requests' => $totalRequests,
                'total_tokens' => $totalTokens,
                'raw_cost_usd' => $totalRawCost,
                'margin_percentage' => $marginPercentage,
                'margin_amount_usd' => $marginAmount,
                'billed_amount_usd' => $billedAmount,
                'status' => 'pending',
                'due_date' => $dueDate->toDateString(),
                'generated_at' => now(),
            ]);

            // Get feature names for line items
            $featureNames = AIFeatureDefinition::pluck('name_ar', 'slug')
                ->merge(AIFeatureDefinition::pluck('name', 'slug')->mapWithKeys(fn ($v, $k) => [$k . '_en' => $v]));

            foreach ($featureBreakdown as $row) {
                $featureRawCost = (float) $row->raw_cost;
                $featureBilledCost = round($featureRawCost * (1 + $marginPercentage / 100), 5);

                AIBillingInvoiceItem::create([
                    'ai_billing_invoice_id' => $invoice->id,
                    'feature_slug' => $row->feature_slug,
                    'feature_name' => $featureNames[$row->feature_slug . '_en'] ?? $row->feature_slug,
                    'feature_name_ar' => $featureNames[$row->feature_slug] ?? $row->feature_slug,
                    'request_count' => $row->request_count,
                    'total_tokens' => $row->total_tokens,
                    'raw_cost_usd' => $featureRawCost,
                    'billed_cost_usd' => $featureBilledCost,
                    'created_at' => now(),
                ]);
            }

            return $invoice->load('items');
        });
    }

    // ─── Payment Management ──────────────────────────────────────

    /**
     * Record a payment for an invoice and update its status.
     */
    public function recordPayment(
        string $invoiceId,
        float $amountUsd,
        string $paymentMethod = 'manual',
        ?string $reference = null,
        ?string $notes = null,
        ?string $recordedBy = null,
    ): AIBillingPayment {
        $invoice = AIBillingInvoice::findOrFail($invoiceId);

        $payment = AIBillingPayment::create([
            'ai_billing_invoice_id' => $invoiceId,
            'amount_usd' => $amountUsd,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'notes' => $notes,
            'recorded_by' => $recordedBy,
            'created_at' => now(),
        ]);

        // Update invoice status
        $totalPaid = $invoice->payments()->sum('amount_usd');
        if ($totalPaid >= (float) $invoice->billed_amount_usd) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $reference,
                'payment_notes' => $notes,
            ]);

            // Re-enable store if it was disabled due to overdue
            $config = AIStoreBillingConfig::where('store_id', $invoice->store_id)->first();
            if ($config && !$config->is_ai_enabled && $config->disabled_reason === 'overdue_invoice') {
                $config->update([
                    'is_ai_enabled' => true,
                    'disabled_reason' => null,
                    'disabled_at' => null,
                    'enabled_at' => now(),
                ]);
            }
        }

        return $payment;
    }

    /**
     * Mark an invoice as paid (full amount, convenience method).
     */
    public function markInvoicePaid(
        string $invoiceId,
        string $paymentMethod = 'manual',
        ?string $reference = null,
        ?string $notes = null,
        ?string $recordedBy = null,
    ): AIBillingInvoice {
        $invoice = AIBillingInvoice::findOrFail($invoiceId);
        $remaining = $invoice->remaining_amount;

        if ($remaining > 0) {
            $this->recordPayment($invoiceId, $remaining, $paymentMethod, $reference, $notes, $recordedBy);
        } else {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $reference,
                'payment_notes' => $notes,
            ]);
        }

        return $invoice->fresh(['items', 'payments']);
    }

    // ─── Auto-Disable Overdue Stores ─────────────────────────────

    /**
     * Check overdue invoices and disable stores that haven't paid within grace period.
     */
    public function checkAndDisableOverdueStores(): array
    {
        if (!AIBillingSetting::getBool('billing_enabled', true)) {
            return ['disabled' => 0, 'stores' => []];
        }

        $graceDays = AIBillingSetting::getInt('auto_disable_grace_days', 5);
        $cutoffDate = now()->toDateString();

        // Find pending invoices past due date
        $overdueInvoices = AIBillingInvoice::where('status', 'pending')
            ->where('due_date', '<', $cutoffDate)
            ->get();

        $disabledStores = [];

        foreach ($overdueInvoices as $invoice) {
            // Mark invoice as overdue
            $invoice->update(['status' => 'overdue']);

            // Disable the store's AI access
            $config = AIStoreBillingConfig::getOrCreateForStore($invoice->store_id, $invoice->organization_id);
            if ($config->is_ai_enabled) {
                $config->update([
                    'is_ai_enabled' => false,
                    'disabled_reason' => 'overdue_invoice',
                    'disabled_at' => now(),
                ]);
                $disabledStores[] = $invoice->store_id;
            }
        }

        return [
            'disabled' => count($disabledStores),
            'overdue_invoices' => $overdueInvoices->count(),
            'stores' => $disabledStores,
        ];
    }

    // ─── Store Billing Management ────────────────────────────────

    /**
     * Enable AI for a store.
     */
    public function enableStoreAI(string $storeId, string $organizationId): AIStoreBillingConfig
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($storeId, $organizationId);
        $config->update([
            'is_ai_enabled' => true,
            'disabled_reason' => null,
            'disabled_at' => null,
            'enabled_at' => now(),
        ]);
        return $config->fresh();
    }

    /**
     * Disable AI for a store (manual admin action).
     */
    public function disableStoreAI(string $storeId, string $organizationId, string $reason = 'disabled_by_admin'): AIStoreBillingConfig
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($storeId, $organizationId);
        $config->update([
            'is_ai_enabled' => false,
            'disabled_reason' => $reason,
            'disabled_at' => now(),
        ]);
        return $config->fresh();
    }

    /**
     * Update store billing config (monthly limit, margin, notes).
     */
    public function updateStoreBillingConfig(string $storeId, string $organizationId, array $data): AIStoreBillingConfig
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($storeId, $organizationId);
        $config->update(array_intersect_key($data, array_flip([
            'monthly_limit_usd',
            'custom_margin_percentage',
            'notes',
        ])));
        return $config->fresh();
    }

    // ─── Billing Summary (for store-side dashboard) ──────────────

    /**
     * Get comprehensive billing summary for a store.
     */
    public function getStoreBillingSummary(string $storeId, string $organizationId): array
    {
        $config = AIStoreBillingConfig::getOrCreateForStore($storeId, $organizationId);
        $marginPercentage = $config->getEffectiveMarginPercentage();

        // Current month usage
        $monthStart = now()->startOfMonth();
        $currentUsage = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', $monthStart)
            ->where('status', 'success')
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as raw_cost,
                SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd * (1 + ? / 100) END) as billed_cost
            ", [$marginPercentage])
            ->first();

        $rawCost = round((float) ($currentUsage->raw_cost ?? 0), 5);
        $billedCost = round((float) ($currentUsage->billed_cost ?? 0), 5);
        $marginAmount = round($billedCost - $rawCost, 5);

        // Current month by feature
        $byFeature = AIUsageLog::where('store_id', $storeId)
            ->where('created_at', '>=', $monthStart)
            ->where('status', 'success')
            ->groupBy('feature_slug')
            ->selectRaw("
                feature_slug,
                COUNT(*) as request_count,
                SUM(total_tokens) as total_tokens,
                SUM(CASE WHEN billed_cost_usd > 0 THEN billed_cost_usd ELSE estimated_cost_usd * (1 + ? / 100) END) as billed_cost
            ", [$marginPercentage])
            ->orderByDesc('billed_cost')
            ->get()
            ->map(fn ($row) => [
                'feature_slug' => $row->feature_slug,
                'request_count' => (int) $row->request_count,
                'total_tokens' => (int) $row->total_tokens,
                'billed_cost_usd' => round((float) $row->billed_cost, 5),
            ]);

        // Monthly limit info
        $storeLimit = (float) $config->monthly_limit_usd;
        $globalLimit = AIBillingSetting::getFloat('global_monthly_limit_usd', 0);
        $effectiveLimit = $storeLimit > 0 ? $storeLimit : ($globalLimit > 0 ? $globalLimit : 0);
        $limitPercentage = $effectiveLimit > 0 ? round(($billedCost / $effectiveLimit) * 100, 1) : 0;

        // Recent invoices
        $recentInvoices = AIBillingInvoice::forStore($storeId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(6)
            ->get();

        return [
            'config' => [
                'is_ai_enabled' => $config->is_ai_enabled,
                'monthly_limit_usd' => (float) $config->monthly_limit_usd,
                'effective_limit_usd' => $effectiveLimit,
                'disabled_reason' => $config->disabled_reason,
                'disabled_at' => $config->disabled_at?->toIso8601String(),
            ],
            'current_month' => [
                'year' => now()->year,
                'month' => now()->month,
                'total_requests' => (int) ($currentUsage->total_requests ?? 0),
                'total_tokens' => (int) ($currentUsage->total_tokens ?? 0),
                'billed_cost_usd' => $billedCost,
                'limit_usd' => $effectiveLimit,
                'limit_percentage' => $limitPercentage,
                'by_feature' => $byFeature,
            ],
            'recent_invoices' => $recentInvoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'year' => $inv->year,
                'month' => $inv->month,
                'billed_amount_usd' => (float) $inv->billed_amount_usd,
                'status' => $inv->status,
                'due_date' => $inv->due_date->toDateString(),
                'paid_at' => $inv->paid_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Get detailed invoice for store view.
     */
    public function getInvoiceDetail(string $invoiceId, string $storeId): ?array
    {
        $invoice = AIBillingInvoice::with(['items', 'payments'])
            ->where('id', $invoiceId)
            ->where('store_id', $storeId)
            ->first();

        if (!$invoice) return null;

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'year' => $invoice->year,
            'month' => $invoice->month,
            'period_start' => $invoice->period_start->toDateString(),
            'period_end' => $invoice->period_end->toDateString(),
            'total_requests' => $invoice->total_requests,
            'total_tokens' => $invoice->total_tokens,
            'billed_amount_usd' => (float) $invoice->billed_amount_usd,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'payment_reference' => $invoice->payment_reference,
            'items' => $invoice->items->map(fn ($item) => [
                'feature_slug' => $item->feature_slug,
                'feature_name' => $item->feature_name,
                'feature_name_ar' => $item->feature_name_ar,
                'request_count' => $item->request_count,
                'total_tokens' => $item->total_tokens,
                'billed_cost_usd' => (float) $item->billed_cost_usd,
            ]),
            'payments' => $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount_usd' => (float) $p->amount_usd,
                'payment_method' => $p->payment_method,
                'reference' => $p->reference,
                'notes' => $p->notes,
                'created_at' => $p->created_at->toIso8601String(),
            ]),
        ];
    }

    // ─── Admin Dashboard ─────────────────────────────────────────

    /**
     * Get billing overview for admin dashboard.
     */
    public function getAdminBillingOverview(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        // All settings
        $settings = AIBillingSetting::getAllSettings();

        // Invoice stats for the period
        $invoiceStats = AIBillingInvoice::forPeriod($year, $month)
            ->selectRaw("
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(raw_cost_usd) as total_raw_cost,
                SUM(margin_amount_usd) as total_margin,
                SUM(billed_amount_usd) as total_billed,
                SUM(CASE WHEN status = 'paid' THEN billed_amount_usd ELSE 0 END) as total_collected
            ")
            ->first();

        // Store billing configs
        $storeStats = AIStoreBillingConfig::selectRaw("
                COUNT(*) as total_stores,
                SUM(CASE WHEN is_ai_enabled = true THEN 1 ELSE 0 END) as enabled_stores,
                SUM(CASE WHEN is_ai_enabled = false THEN 1 ELSE 0 END) as disabled_stores
            ")
            ->first();

        // Top stores by spending this month
        $topStores = AIBillingInvoice::forPeriod($year, $month)
            ->with('store:id,name')
            ->orderByDesc('billed_amount_usd')
            ->limit(10)
            ->get()
            ->map(fn ($inv) => [
                'store_id' => $inv->store_id,
                'store_name' => $inv->store?->name ?? 'Unknown',
                'billed_amount_usd' => (float) $inv->billed_amount_usd,
                'status' => $inv->status,
            ]);

        // Revenue trend (last 6 months)
        $revenueTrend = AIBillingInvoice::selectRaw("
                year, month,
                SUM(billed_amount_usd) as total_billed,
                SUM(CASE WHEN status = 'paid' THEN billed_amount_usd ELSE 0 END) as total_collected,
                COUNT(*) as invoice_count
            ")
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(6)
            ->get();

        return [
            'settings' => $settings,
            'period' => ['year' => $year, 'month' => $month],
            'invoice_stats' => [
                'total_invoices' => (int) ($invoiceStats->total_invoices ?? 0),
                'paid_count' => (int) ($invoiceStats->paid_count ?? 0),
                'pending_count' => (int) ($invoiceStats->pending_count ?? 0),
                'overdue_count' => (int) ($invoiceStats->overdue_count ?? 0),
                'total_raw_cost_usd' => round((float) ($invoiceStats->total_raw_cost ?? 0), 5),
                'total_margin_usd' => round((float) ($invoiceStats->total_margin ?? 0), 5),
                'total_billed_usd' => round((float) ($invoiceStats->total_billed ?? 0), 5),
                'total_collected_usd' => round((float) ($invoiceStats->total_collected ?? 0), 5),
            ],
            'store_stats' => [
                'total_stores' => (int) ($storeStats->total_stores ?? 0),
                'enabled_stores' => (int) ($storeStats->enabled_stores ?? 0),
                'disabled_stores' => (int) ($storeStats->disabled_stores ?? 0),
            ],
            'top_stores' => $topStores,
            'revenue_trend' => $revenueTrend,
        ];
    }

    /**
     * Get all invoices for admin (with pagination & filters).
     */
    public function getAdminInvoices(array $filters = [], int $perPage = 20)
    {
        $query = AIBillingInvoice::with(['store:id,name'])
            ->when($filters['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('year', $v))
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->where('month', $v))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    /**
     * Get all store billing configs for admin (with pagination & filters).
     */
    public function getAdminStoreConfigs(array $filters = [], int $perPage = 20)
    {
        $query = AIStoreBillingConfig::with(['store:id,name'])
            ->when(isset($filters['is_ai_enabled']), fn ($q) => $q->where('is_ai_enabled', $filters['is_ai_enabled']))
            ->when($filters['organization_id'] ?? null, fn ($q, $v) => $q->where('organization_id', $v))
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }
}
