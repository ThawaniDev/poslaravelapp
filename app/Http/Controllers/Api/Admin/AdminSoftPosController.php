<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Core\Models\Register;
use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use App\Domain\ProviderSubscription\Services\SoftPosFeeService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin SoftPOS financial management:
 *
 *  GET  /admin/softpos/transactions     — paginated list with full fee breakdown
 *  GET  /admin/softpos/financials       — aggregated P&L summary
 *  GET  /admin/softpos/terminal-rates   — list all terminal billing configs
 */
class AdminSoftPosController extends BaseApiController
{
    public function __construct(private readonly SoftPosFeeService $feeService)
    {
    }

    // ═══════════════════════════════════════════════════════════
    // Transaction Listing (with full financial breakdown)
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/softpos/transactions
     *
     * Query params:
     *   date_from    (Y-m-d, default 30 days ago)
     *   date_to      (Y-m-d, default today)
     *   store_id     (optional filter)
     *   terminal_id  (optional filter)
     *   card_scheme  (optional filter: mada|visa|mastercard|...)
     *   fee_type     (optional filter: percentage|fixed)
     *   per_page     (default 25, max 100)
     *   page         (default 1)
     */
    public function transactions(Request $request): JsonResponse
    {
        $dateFrom   = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo     = $request->query('date_to', now()->toDateString());
        $storeId    = $request->query('store_id');
        $terminalId = $request->query('terminal_id');
        $scheme     = $request->query('card_scheme');
        $feeType    = $request->query('fee_type');
        $perPage    = min((int) $request->query('per_page', 25), 100);

        $query = SoftPosTransaction::with(['store:id,name', 'terminal:id,name,code'])
            ->whereBetween(DB::raw('DATE(softpos_transactions.created_at)'), [$dateFrom, $dateTo])
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId))
            ->when($scheme,     fn ($q) => $q->whereRaw('LOWER(payment_method) = ?', [strtolower($scheme)]))
            ->when($feeType,    fn ($q) => $q->where('fee_type', $feeType))
            ->orderByDesc('created_at');

        $paginated = $query->paginate($perPage);

        // Page-level aggregates
        $pageItems = $paginated->getCollection();
        $summary = [
            'total_amount'      => round($pageItems->sum('amount'), 3),
            'total_platform_fee' => round($pageItems->sum('platform_fee'), 3),
            'total_gateway_fee' => round($pageItems->sum('gateway_fee'), 3),
            'total_margin'      => round($pageItems->sum('margin'), 3),
        ];

        $items = $pageItems->map(fn (SoftPosTransaction $t) => $this->formatTransaction($t));

        return $this->success([
            'data'       => $items,
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
            'page_summary' => $summary,
            'date_range'   => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Financial P&L Summary
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/softpos/financials
     *
     * Returns comprehensive P&L breakdown for SoftPOS transactions.
     *
     * Query params: date_from, date_to, store_id, terminal_id
     */
    public function financials(Request $request): JsonResponse
    {
        $dateFrom   = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo     = $request->query('date_to', now()->toDateString());
        $storeId    = $request->query('store_id');
        $terminalId = $request->query('terminal_id');

        $base = SoftPosTransaction::where('status', 'completed')
            ->whereBetween(DB::raw('DATE(softpos_transactions.created_at)'), [$dateFrom, $dateTo])
            ->when($storeId,    fn ($q) => $q->where('softpos_transactions.store_id', $storeId))
            ->when($terminalId, fn ($q) => $q->where('softpos_transactions.terminal_id', $terminalId));

        // ── Top-line KPIs ─────────────────────────────────────────────
        $totals = (clone $base)->selectRaw(
            'COUNT(*) as txn_count,
             SUM(amount) as total_volume,
             SUM(platform_fee) as total_platform_fee,
             SUM(gateway_fee) as total_gateway_fee,
             SUM(margin) as total_margin,
             AVG(amount) as avg_transaction_amount,
             AVG(platform_fee) as avg_platform_fee,
             AVG(margin) as avg_margin'
        )->first();

        // ── By card scheme ────────────────────────────────────────────
        $byScheme = (clone $base)
            ->selectRaw(
                'LOWER(payment_method) as scheme,
                 fee_type,
                 COUNT(*) as txn_count,
                 SUM(amount) as volume,
                 SUM(platform_fee) as platform_fees,
                 SUM(gateway_fee) as gateway_fees,
                 SUM(margin) as margin'
            )
            ->groupByRaw('LOWER(payment_method), fee_type')
            ->orderByRaw('SUM(margin) DESC')
            ->get()
            ->map(fn ($row) => [
                'scheme'        => $row->scheme ?? 'unknown',
                'fee_type'      => $row->fee_type,
                'txn_count'     => (int) $row->txn_count,
                'volume'        => (float) $row->volume,
                'platform_fees' => (float) $row->platform_fees,
                'gateway_fees'  => (float) $row->gateway_fees,
                'margin'        => (float) $row->margin,
                'margin_rate'   => $row->volume > 0
                    ? round(((float) $row->margin / (float) $row->volume) * 100, 4)
                    : 0,
            ]);

        // ── By terminal ───────────────────────────────────────────────
        $byTerminal = (clone $base)
            ->join('registers', \Illuminate\Support\Facades\DB::raw('registers.id::text'), '=', 'softpos_transactions.terminal_id')
            ->selectRaw(
                'softpos_transactions.terminal_id,
                 registers.name as terminal_name,
                 registers.code as terminal_code,
                 COUNT(*) as txn_count,
                 SUM(softpos_transactions.amount) as volume,
                 SUM(softpos_transactions.platform_fee) as platform_fees,
                 SUM(softpos_transactions.gateway_fee) as gateway_fees,
                 SUM(softpos_transactions.margin) as margin'
            )
            ->groupBy('softpos_transactions.terminal_id', 'registers.name', 'registers.code')
            ->orderByRaw('SUM(softpos_transactions.margin) DESC')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'terminal_id'   => $row->terminal_id,
                'terminal_name' => $row->terminal_name,
                'terminal_code' => $row->terminal_code,
                'txn_count'     => (int) $row->txn_count,
                'volume'        => (float) $row->volume,
                'platform_fees' => (float) $row->platform_fees,
                'gateway_fees'  => (float) $row->gateway_fees,
                'margin'        => (float) $row->margin,
            ]);

        // ── By store ──────────────────────────────────────────────────
        $byStore = (clone $base)
            ->join('stores', 'stores.id', '=', 'softpos_transactions.store_id')
            ->selectRaw(
                'softpos_transactions.store_id,
                 stores.name as store_name,
                 COUNT(*) as txn_count,
                 SUM(softpos_transactions.amount) as volume,
                 SUM(softpos_transactions.platform_fee) as platform_fees,
                 SUM(softpos_transactions.gateway_fee) as gateway_fees,
                 SUM(softpos_transactions.margin) as margin'
            )
            ->groupBy('softpos_transactions.store_id', 'stores.name')
            ->orderByRaw('SUM(softpos_transactions.margin) DESC')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'store_id'      => $row->store_id,
                'store_name'    => $row->store_name,
                'txn_count'     => (int) $row->txn_count,
                'volume'        => (float) $row->volume,
                'platform_fees' => (float) $row->platform_fees,
                'gateway_fees'  => (float) $row->gateway_fees,
                'margin'        => (float) $row->margin,
            ]);

        // ── Daily trend ───────────────────────────────────────────────
        $daily = (clone $base)
            ->selectRaw(
                'DATE(softpos_transactions.created_at) as date,
                 COUNT(*) as txn_count,
                 SUM(softpos_transactions.amount) as volume,
                 SUM(softpos_transactions.platform_fee) as platform_fees,
                 SUM(softpos_transactions.gateway_fee) as gateway_fees,
                 SUM(softpos_transactions.margin) as margin'
            )
            ->groupByRaw('DATE(softpos_transactions.created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->map(fn ($row) => [
                'date'          => $row->date,
                'txn_count'     => (int) $row->txn_count,
                'volume'        => (float) $row->volume,
                'platform_fees' => (float) $row->platform_fees,
                'gateway_fees'  => (float) $row->gateway_fees,
                'margin'        => (float) $row->margin,
            ]);

        return $this->success([
            'kpis' => [
                'transaction_count'    => (int) ($totals->txn_count ?? 0),
                'total_volume'         => round((float) ($totals->total_volume ?? 0), 3),
                'total_platform_fee'   => round((float) ($totals->total_platform_fee ?? 0), 3),
                'total_gateway_fee'    => round((float) ($totals->total_gateway_fee ?? 0), 3),
                'total_margin'         => round((float) ($totals->total_margin ?? 0), 3),
                'avg_transaction'      => round((float) ($totals->avg_transaction_amount ?? 0), 3),
                'avg_platform_fee'     => round((float) ($totals->avg_platform_fee ?? 0), 3),
                'avg_margin'           => round((float) ($totals->avg_margin ?? 0), 3),
                'overall_margin_rate'  => ($totals->total_volume ?? 0) > 0
                    ? round(
                        ((float) ($totals->total_margin ?? 0) / (float) $totals->total_volume) * 100,
                        4
                    )
                    : 0,
            ],
            'by_scheme'     => $byScheme,
            'by_terminal'   => $byTerminal,
            'by_store'      => $byStore,
            'daily_trend'   => $daily,
            'date_range'    => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Terminal Rate Card
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /admin/softpos/terminal-rates
     *
     * Returns the billing rate configuration for every SoftPOS-enabled terminal.
     */
    public function terminalRates(Request $request): JsonResponse
    {
        $terminals = Register::with('store:id,name')
            ->where('softpos_enabled', true)
            ->orderBy('name')
            ->get();

        $data = $terminals->map(fn (Register $r) => [
            'id'           => $r->id,
            'name'         => $r->name,
            'code'         => $r->code,
            'store_id'     => $r->store_id,
            'store_name'   => $r->store?->name,
            'softpos_status' => $r->softpos_status,
            'billing' => [
                // Mada
                'mada_merchant_rate'     => (float) ($r->softpos_mada_merchant_rate ?? 0.006),
                'mada_gateway_rate'      => (float) ($r->softpos_mada_gateway_rate  ?? 0.004),
                'mada_margin_rate'       => round(
                    (float) ($r->softpos_mada_merchant_rate ?? 0.006) - (float) ($r->softpos_mada_gateway_rate ?? 0.004), 6
                ),
                'mada_merchant_rate_pct' => round((float) ($r->softpos_mada_merchant_rate ?? 0.006) * 100, 4),
                'mada_gateway_rate_pct'  => round((float) ($r->softpos_mada_gateway_rate  ?? 0.004) * 100, 4),
                'mada_margin_rate_pct'   => round((
                    (float) ($r->softpos_mada_merchant_rate ?? 0.006) - (float) ($r->softpos_mada_gateway_rate ?? 0.004)
                ) * 100, 4),
                // Visa / Mastercard
                'card_merchant_fee'      => (float) ($r->softpos_card_merchant_fee ?? 1.000),
                'card_gateway_fee'       => (float) ($r->softpos_card_gateway_fee  ?? 0.500),
                'card_margin_fee'        => round(
                    (float) ($r->softpos_card_merchant_fee ?? 1.000) - (float) ($r->softpos_card_gateway_fee ?? 0.500), 3
                ),
                // Human-readable
                'merchant_description'   => $this->feeService->merchantFeeDescription(
                    null,
                    (float) ($r->softpos_mada_merchant_rate ?? 0.006),
                    (float) ($r->softpos_card_merchant_fee  ?? 1.000),
                ),
            ],
        ]);

        return $this->success(['terminals' => $data]);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function formatTransaction(SoftPosTransaction $t): array
    {
        return [
            'id'            => $t->id,
            'order_id'      => $t->order_id,
            'store'         => $t->store ? ['id' => $t->store_id, 'name' => $t->store->name] : null,
            'terminal'      => $t->terminal ? [
                'id'   => $t->terminal_id,
                'name' => $t->terminal->name,
                'code' => $t->terminal->code,
            ] : null,
            'amount'        => (float) $t->amount,
            'currency'      => $t->currency ?? 'SAR',
            'card_scheme'   => $t->payment_method,
            'fee_type'      => $t->fee_type,
            'fees' => [
                'platform_fee' => (float) $t->platform_fee,
                'gateway_fee'  => (float) $t->gateway_fee,
                'margin'       => (float) $t->margin,
            ],
            'transaction_ref' => $t->transaction_ref,
            'status'          => $t->status,
            'created_at'      => $t->created_at?->toISOString(),
        ];
    }
}
