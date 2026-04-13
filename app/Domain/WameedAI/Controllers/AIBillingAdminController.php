<?php

namespace App\Domain\WameedAI\Controllers;

use App\Domain\WameedAI\Models\AIBillingInvoice;
use App\Domain\WameedAI\Models\AIBillingSetting;
use App\Domain\WameedAI\Models\AIStoreBillingConfig;
use App\Domain\WameedAI\Services\AIBillingService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIBillingAdminController extends BaseApiController
{
    public function __construct(
        private readonly AIBillingService $billingService,
    ) {}

    // ─── Billing Settings ────────────────────────────────────────

    /**
     * GET /admin/wameed-ai/billing/settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = AIBillingSetting::all()->map(fn ($s) => [
            'id' => $s->id,
            'key' => $s->key,
            'value' => $s->value,
            'description' => $s->description,
        ]);

        return $this->success($settings);
    }

    /**
     * PUT /admin/wameed-ai/billing/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'required|string|max:500',
        ]);

        foreach ($request->input('settings') as $setting) {
            AIBillingSetting::setValue($setting['key'], $setting['value']);
        }

        return $this->success(AIBillingSetting::all()->map(fn ($s) => [
            'id' => $s->id,
            'key' => $s->key,
            'value' => $s->value,
            'description' => $s->description,
        ]));
    }

    // ─── Billing Dashboard ───────────────────────────────────────

    /**
     * GET /admin/wameed-ai/billing/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        return $this->success($this->billingService->getAdminBillingOverview((int) $year, (int) $month));
    }

    // ─── Invoice Management ──────────────────────────────────────

    /**
     * GET /admin/wameed-ai/billing/invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        $filters = $request->only(['store_id', 'status', 'year', 'month']);
        $perPage = (int) $request->query('per_page', 20);

        $invoices = $this->billingService->getAdminInvoices($filters, $perPage);

        $data = $invoices->getCollection()->map(fn ($inv) => [
            'id' => $inv->id,
            'store_id' => $inv->store_id,
            'store_name' => $inv->store?->name ?? 'Unknown',
            'invoice_number' => $inv->invoice_number,
            'year' => $inv->year,
            'month' => $inv->month,
            'total_requests' => $inv->total_requests,
            'raw_cost_usd' => (float) $inv->raw_cost_usd,
            'margin_percentage' => (float) $inv->margin_percentage,
            'billed_amount_usd' => (float) $inv->billed_amount_usd,
            'status' => $inv->status,
            'due_date' => $inv->due_date->toDateString(),
            'paid_at' => $inv->paid_at?->toIso8601String(),
        ]);

        return $this->successPaginated($data, $invoices);
    }

    /**
     * GET /admin/wameed-ai/billing/invoices/{invoiceId}
     */
    public function invoiceDetail(string $invoiceId): JsonResponse
    {
        $invoice = AIBillingInvoice::with(['items', 'payments', 'store:id,name'])->findOrFail($invoiceId);

        return $this->success([
            'id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'store_name' => $invoice->store?->name ?? 'Unknown',
            'invoice_number' => $invoice->invoice_number,
            'year' => $invoice->year,
            'month' => $invoice->month,
            'period_start' => $invoice->period_start->toDateString(),
            'period_end' => $invoice->period_end->toDateString(),
            'total_requests' => $invoice->total_requests,
            'total_tokens' => $invoice->total_tokens,
            'raw_cost_usd' => (float) $invoice->raw_cost_usd,
            'margin_percentage' => (float) $invoice->margin_percentage,
            'margin_amount_usd' => (float) $invoice->margin_amount_usd,
            'billed_amount_usd' => (float) $invoice->billed_amount_usd,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'payment_reference' => $invoice->payment_reference,
            'payment_notes' => $invoice->payment_notes,
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'feature_slug' => $item->feature_slug,
                'feature_name' => $item->feature_name,
                'feature_name_ar' => $item->feature_name_ar,
                'request_count' => $item->request_count,
                'total_tokens' => $item->total_tokens,
                'raw_cost_usd' => (float) $item->raw_cost_usd,
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
        ]);
    }

    /**
     * POST /admin/wameed-ai/billing/invoices/{invoiceId}/mark-paid
     */
    public function markInvoicePaid(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'payment_method' => 'sometimes|string|max:50',
            'reference' => 'sometimes|string|max:255',
            'notes' => 'sometimes|string|max:1000',
        ]);

        $invoice = $this->billingService->markInvoicePaid(
            $invoiceId,
            $request->input('payment_method', 'manual'),
            $request->input('reference'),
            $request->input('notes'),
            $request->user()?->id,
        );

        return $this->success([
            'id' => $invoice->id,
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'billed_amount_usd' => (float) $invoice->billed_amount_usd,
        ]);
    }

    /**
     * POST /admin/wameed-ai/billing/invoices/{invoiceId}/record-payment
     */
    public function recordPayment(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'amount_usd' => 'required|numeric|min:0.001',
            'payment_method' => 'sometimes|string|max:50',
            'reference' => 'sometimes|string|max:255',
            'notes' => 'sometimes|string|max:1000',
        ]);

        $payment = $this->billingService->recordPayment(
            $invoiceId,
            (float) $request->input('amount_usd'),
            $request->input('payment_method', 'manual'),
            $request->input('reference'),
            $request->input('notes'),
            $request->user()?->id,
        );

        return $this->created([
            'id' => $payment->id,
            'amount_usd' => (float) $payment->amount_usd,
            'payment_method' => $payment->payment_method,
        ]);
    }

    /**
     * POST /admin/wameed-ai/billing/generate-invoices
     */
    public function generateInvoices(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'sometimes|integer|min:2024|max:2050',
            'month' => 'sometimes|integer|min:1|max:12',
        ]);

        $result = $this->billingService->generateMonthlyInvoices(
            $request->input('year'),
            $request->input('month'),
        );

        return $this->success($result);
    }

    /**
     * POST /admin/wameed-ai/billing/check-overdue
     */
    public function checkOverdue(): JsonResponse
    {
        $result = $this->billingService->checkAndDisableOverdueStores();
        return $this->success($result);
    }

    // ─── Store Billing Config Management ─────────────────────────

    /**
     * GET /admin/wameed-ai/billing/stores
     */
    public function storeConfigs(Request $request): JsonResponse
    {
        $filters = $request->only(['is_ai_enabled', 'organization_id']);
        if (isset($filters['is_ai_enabled'])) {
            $filters['is_ai_enabled'] = filter_var($filters['is_ai_enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        $perPage = (int) $request->query('per_page', 20);

        $configs = $this->billingService->getAdminStoreConfigs($filters, $perPage);

        $data = $configs->getCollection()->map(fn ($cfg) => [
            'id' => $cfg->id,
            'store_id' => $cfg->store_id,
            'store_name' => $cfg->store?->name ?? 'Unknown',
            'organization_id' => $cfg->organization_id,
            'is_ai_enabled' => $cfg->is_ai_enabled,
            'monthly_limit_usd' => (float) $cfg->monthly_limit_usd,
            'custom_margin_percentage' => $cfg->custom_margin_percentage !== null ? (float) $cfg->custom_margin_percentage : null,
            'disabled_reason' => $cfg->disabled_reason,
            'disabled_at' => $cfg->disabled_at?->toIso8601String(),
            'enabled_at' => $cfg->enabled_at?->toIso8601String(),
            'notes' => $cfg->notes,
        ]);

        return $this->successPaginated($data, $configs);
    }

    /**
     * GET /admin/wameed-ai/billing/stores/{storeId}
     */
    public function storeConfigDetail(string $storeId): JsonResponse
    {
        $config = AIStoreBillingConfig::with('store:id,name')
            ->where('store_id', $storeId)
            ->firstOrFail();

        // Also fetch recent invoices for this store
        $invoices = AIBillingInvoice::forStore($storeId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get();

        return $this->success([
            'config' => [
                'id' => $config->id,
                'store_id' => $config->store_id,
                'store_name' => $config->store?->name ?? 'Unknown',
                'is_ai_enabled' => $config->is_ai_enabled,
                'monthly_limit_usd' => (float) $config->monthly_limit_usd,
                'custom_margin_percentage' => $config->custom_margin_percentage !== null ? (float) $config->custom_margin_percentage : null,
                'disabled_reason' => $config->disabled_reason,
                'disabled_at' => $config->disabled_at?->toIso8601String(),
                'enabled_at' => $config->enabled_at?->toIso8601String(),
                'notes' => $config->notes,
            ],
            'invoices' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'year' => $inv->year,
                'month' => $inv->month,
                'billed_amount_usd' => (float) $inv->billed_amount_usd,
                'status' => $inv->status,
                'due_date' => $inv->due_date->toDateString(),
                'paid_at' => $inv->paid_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * PUT /admin/wameed-ai/billing/stores/{storeId}
     */
    public function updateStoreConfig(Request $request, string $storeId): JsonResponse
    {
        $request->validate([
            'monthly_limit_usd' => 'sometimes|numeric|min:0',
            'custom_margin_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $store = \App\Domain\Core\Models\Store::findOrFail($storeId);

        $config = $this->billingService->updateStoreBillingConfig(
            $storeId,
            $store->organization_id,
            $request->only(['monthly_limit_usd', 'custom_margin_percentage', 'notes']),
        );

        return $this->success([
            'id' => $config->id,
            'store_id' => $config->store_id,
            'monthly_limit_usd' => (float) $config->monthly_limit_usd,
            'custom_margin_percentage' => $config->custom_margin_percentage !== null ? (float) $config->custom_margin_percentage : null,
            'notes' => $config->notes,
        ]);
    }

    /**
     * POST /admin/wameed-ai/billing/stores/{storeId}/toggle-ai
     */
    public function toggleStoreAI(Request $request, string $storeId): JsonResponse
    {
        $store = \App\Domain\Core\Models\Store::findOrFail($storeId);
        $config = AIStoreBillingConfig::where('store_id', $storeId)->first();

        if ($config && $config->is_ai_enabled) {
            $reason = $request->input('reason', 'disabled_by_admin');
            $config = $this->billingService->disableStoreAI($storeId, $store->organization_id, $reason);
        } else {
            $config = $this->billingService->enableStoreAI($storeId, $store->organization_id);
        }

        return $this->success([
            'store_id' => $config->store_id,
            'is_ai_enabled' => $config->is_ai_enabled,
            'disabled_reason' => $config->disabled_reason,
            'disabled_at' => $config->disabled_at?->toIso8601String(),
            'enabled_at' => $config->enabled_at?->toIso8601String(),
        ]);
    }
}
