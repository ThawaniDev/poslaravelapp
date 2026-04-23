<?php

namespace App\Domain\PosTerminal\Controllers\Api;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Resources\ProductResource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Resources\CustomerResource;
use App\Domain\PosTerminal\Requests\ApplyInventoryAdjustmentsRequest;
use App\Domain\PosTerminal\Requests\BatchCloseSessionsRequest;
use App\Domain\PosTerminal\Requests\BatchTransactionsRequest;
use App\Domain\PosTerminal\Requests\CloseSessionRequest;
use App\Domain\PosTerminal\Requests\CreateReturnRequest;
use App\Domain\PosTerminal\Requests\CreateTransactionRequest;
use App\Domain\PosTerminal\Requests\HoldCartRequest;
use App\Domain\PosTerminal\Requests\OpenSessionRequest;
use App\Domain\PosTerminal\Requests\UpdateTransactionNotesRequest;
use App\Domain\PosTerminal\Requests\VerifyManagerPinRequest;
use App\Domain\PosTerminal\Requests\VoidTransactionRequest;
use App\Domain\PosTerminal\Resources\HeldCartResource;
use App\Domain\PosTerminal\Resources\PosSessionResource;
use App\Domain\PosTerminal\Resources\TransactionResource;
use App\Domain\PosTerminal\Services\HeldCartService;
use App\Domain\PosTerminal\Services\PosSessionService;
use App\Domain\PosTerminal\Services\TransactionService;
use App\Domain\Subscription\Traits\TracksSubscriptionUsage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTerminalController extends BaseApiController
{
    use TracksSubscriptionUsage;

    public function __construct(
        private readonly PosSessionService $sessionService,
        private readonly TransactionService $transactionService,
        private readonly HeldCartService $heldCartService,
    ) {}

    // ─── Sessions ────────────────────────────────────────────

    public function sessions(Request $request): JsonResponse
    {
        $paginator = $this->sessionService->list(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = PosSessionResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    /**
     * Sessions currently open for the authenticated cashier across any register.
     * Used by the POS client to block opening a second shift while one is already
     * open and to surface the existing shift(s) the user must close first.
     */
    public function myOpenSessions(Request $request): JsonResponse
    {
        $sessions = $this->sessionService->myOpenSessions($request->user());
        return $this->success(PosSessionResource::collection($sessions)->resolve());
    }

    public function openSession(OpenSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->sessionService->open(
                $request->validated(),
                $request->user(),
            );
            return $this->created(new PosSessionResource($session));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

public function showSession(Request $request, string $session): JsonResponse
{
    $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success(new PosSessionResource($found));
    }

    public function closeSession(CloseSessionRequest $request, string $session): JsonResponse
    {
        try {
            $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
            $closed = $this->sessionService->close($found, $request->validated());
            return $this->success(new PosSessionResource($closed));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Reopen a previously closed session for corrections (manager-only).
     */
    public function reopenSession(Request $request, string $session): JsonResponse
    {
        try {
            $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
            $reopened = $this->sessionService->reopen($found);
            return $this->success(new PosSessionResource($reopened));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * End-of-day batch close: close every open session for the user's
     * accessible store(s) using each session's expected_cash.
     */
    public function batchCloseSessions(BatchCloseSessionsRequest $request): JsonResponse
    {
        $accessible = $this->resolvedStoreIds($request);
        $requested = $request->input('store_ids', []);
        $storeIds = !empty($requested)
            ? array_values(array_intersect($accessible, $requested))
            : $accessible;

        if (empty($storeIds)) {
            return $this->error(__('subscription.organization_required'), 422);
        }

        return $this->success($this->sessionService->batchClose($storeIds));
    }

    /**
     * Aggregated session stats by cashier and register.
     */
    public function sessionsSummary(Request $request): JsonResponse
    {
        $storeIds = $this->resolvedStoreIds($request);
        return $this->success($this->sessionService->summary(
            $storeIds,
            $request->query('from'),
            $request->query('to'),
        ));
    }

    // ─── Transactions ────────────────────────────────────────

    public function transactions(Request $request): JsonResponse
    {
        $paginator = $this->transactionService->list(
            $this->resolvedStoreId($request) ?? $request->user()->store_id,
            $request->only(['session_id', 'type', 'status', 'search']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = TransactionResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function createTransaction(CreateTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->create(
                $request->validated(),
                $request->user(),
            );

            if ($orgId = $this->resolveOrganizationId($request)) {
                $this->refreshUsageFor($orgId, 'transactions_per_month');
            }

            return $this->created(new TransactionResource($transaction));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function showTransaction(Request $request, string $transaction): JsonResponse
    {
        $found = $this->transactionService->find($this->resolvedStoreId($request) ?? $request->user()->store_id, $transaction);
        return $this->success(new TransactionResource($found));
    }

    public function showTransactionByNumber(Request $request, string $number): JsonResponse
    {
        try {
            $found = $this->transactionService->findByNumber(
                $this->resolvedStoreId($request) ?? $request->user()->store_id,
                $number,
            );
            return $this->success(new TransactionResource($found));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound(__('pos.transaction_not_found'));
        }
    }

    public function voidTransaction(VoidTransactionRequest $request, string $transaction): JsonResponse
    {
        try {
            $found = $this->transactionService->find($this->resolvedStoreId($request) ?? $request->user()->store_id, $transaction);
            $voided = $this->transactionService->void($found, $request->user(), $request->validated('reason'));
            return $this->success(new TransactionResource($voided));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Update editable fields (notes, customer_id) on an existing transaction.
     */
    public function updateTransactionNotes(UpdateTransactionNotesRequest $request, string $transaction): JsonResponse
    {
        try {
            $found = $this->transactionService->find($this->resolvedStoreId($request) ?? $request->user()->store_id, $transaction);
            $updated = $this->transactionService->updateNotes($found, $request->validated(), $request->user());
            return $this->success(new TransactionResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Stream a CSV export of transactions for the current store scope.
     * Filters: type, status, from, to.
     */
    public function exportTransactions(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->transactionService->exportCsv(
            $this->resolvedStoreIds($request),
            $request->only(['type', 'status', 'from', 'to']),
        );
    }

    public function returnTransaction(CreateReturnRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createReturn(
                $request->validated(),
                $request->user(),
            );
            return $this->created(new TransactionResource($transaction));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function exchangeTransaction(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'return_transaction_id' => ['required', 'string', 'exists:transactions,id'],
            'register_id' => ['nullable', 'string'],
            'pos_session_id' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'string'],
            'returned_items' => ['required', 'array', 'min:1'],
            'returned_items.*.product_id' => ['nullable', 'string'],
            'returned_items.*.product_name' => ['required', 'string'],
            'returned_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'returned_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'returned_items.*.line_total' => ['required', 'numeric'],
            'returned_items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'new_items' => ['required', 'array', 'min:1'],
            'new_items.*.product_id' => ['nullable', 'string'],
            'new_items.*.product_name' => ['required', 'string'],
            'new_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'new_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'new_items.*.line_total' => ['required', 'numeric'],
            'new_items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'string'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
        ]);

        try {
            $result = $this->transactionService->createExchange($validated, $request->user());
            return $this->created([
                'return_transaction' => new TransactionResource($result['return_transaction']),
                'new_transaction' => new TransactionResource($result['new_transaction']),
                'net_amount' => $result['net_amount'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function transactionReceipt(Request $request, string $transaction): JsonResponse
    {
        $found = $this->transactionService->find($this->resolvedStoreId($request) ?? $request->user()->store_id, $transaction);
        $found->load(['transactionItems', 'payments', 'cashier', 'customer']);

        $store = \App\Domain\Core\Models\Store::find($found->store_id);
        $settings = \App\Domain\Core\Models\StoreSettings::where('store_id', $found->store_id)->first();

        return $this->success([
            'transaction' => new TransactionResource($found),
            'store' => [
                'name' => $store?->name,
                'name_ar' => $store?->name_ar,
                'address' => $store?->address,
                'phone' => $store?->phone,
                'email' => $store?->email,
                'logo_url' => $store?->logo_url,
                'tax_number' => $store?->tax_number,
                'cr_number' => $store?->cr_number,
            ],
            'receipt_settings' => [
                'header_text' => $settings?->receipt_header,
                'footer_text' => $settings?->receipt_footer,
                'show_logo' => (bool) $settings?->receipt_show_logo,
                'show_tax_number' => (bool) $settings?->receipt_show_tax_number,
            ],
        ]);
    }

    // ─── Held Carts ──────────────────────────────────────────

    public function heldCarts(Request $request): JsonResponse
    {
        $carts = $this->heldCartService->list($this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success(HeldCartResource::collection($carts)->resolve());
    }

    public function holdCart(HoldCartRequest $request): JsonResponse
    {
        $cart = $this->heldCartService->hold(
            $request->validated(),
            $request->user(),
        );
        return $this->created(new HeldCartResource($cart));
    }

    public function recallCart(Request $request, string $cart): JsonResponse
    {
        try {
            $found = \App\Domain\PosTerminal\Models\HeldCart::findOrFail($cart);
            $recalled = $this->heldCartService->recall($found, $request->user());
            return $this->success(new HeldCartResource($recalled));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function deleteCart(string $cart): JsonResponse
    {
        $found = \App\Domain\PosTerminal\Models\HeldCart::findOrFail($cart);
        $this->heldCartService->delete($found);
        return $this->success(null, 'Held cart deleted.');
    }

    // ─── Cash Events (drop / payout / paid-in) ───────────────

    public function cashEvents(Request $request, string $session): JsonResponse
    {
        $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($this->sessionService->listCashEvents($found));
    }

    public function recordCashEvent(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:cash_in,cash_out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
            $event = $this->sessionService->recordCashEvent($found, $data, $request->user());
            return $this->created($event);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Reports (X / Z) ─────────────────────────────────────

    public function xReport(Request $request, string $session): JsonResponse
    {
        $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($this->sessionService->xReport($found));
    }

    public function zReport(Request $request, string $session): JsonResponse
    {
        $found = $this->sessionService->find($session, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success($this->sessionService->zReport($found));
    }

    // ─── Products (POS catalog) ──────────────────────────────

    public function products(Request $request): JsonResponse
    {
        $query = Product::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', $search);
            });
        }

        if ($request->filled('barcode')) {
            $query->where(function ($q) use ($request) {
                $q->where('barcode', $request->input('barcode'))
                  ->orWhereHas('productBarcodes', function ($q2) use ($request) {
                      $q2->where('barcode', $request->input('barcode'));
                  });
            });
        }

        $paginator = $query->with(['category', 'productImages', 'productBarcodes'])
            ->orderBy('name')
            ->paginate((int) $request->get('per_page', 50));

        $result = $paginator->toArray();
        $result['data'] = ProductResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    // ─── Customers (POS search) ──────────────────────────────

    public function customers(Request $request): JsonResponse
    {
        $query = Customer::where('organization_id', $request->user()->organization_id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('loyalty_code', $search);
            });
        }

        $paginator = $query->orderBy('name')
            ->paginate((int) $request->get('per_page', 20));

        $result = $paginator->toArray();
        $result['data'] = CustomerResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    /**
     * Quick-add a customer from the POS without leaving the cashier screen.
     * Reuses CustomerService so loyalty defaults, phone normalization and
     * organization scoping all stay consistent with the customers feature.
     */
    public function quickAddCustomer(\Illuminate\Http\Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:200'],
            'loyalty_code' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $customer = app(\App\Domain\Customer\Services\CustomerService::class)
                ->create($data, $request->user());
            return $this->created(new CustomerResource($customer));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Manager-PIN step-up ─────────────────────────────────

    public function verifyManagerPin(VerifyManagerPinRequest $request): JsonResponse
    {
        try {
            [$token, $approverId] = app(\App\Domain\PosTerminal\Services\ManagerPinService::class)
                ->verify($request->user(), $request->validated('pin'), $request->validated('action'));

            return $this->success([
                'approval_token' => $token,
                'approver_id' => $approverId,
                'expires_in' => 300,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Offline sync ────────────────────────────────────────

    /**
     * Bulk upload of transactions captured while the register was offline.
     * Idempotent on `client_uuid`. Returns per-entry status so the client can
     * retry only the failures.
     */
    public function batchTransactions(BatchTransactionsRequest $request): JsonResponse
    {
        $results = $this->transactionService->createBatch(
            $request->validated('transactions'),
            $request->user(),
        );

        $created = collect($results)->where('status', 'created')->count();
        if ($created > 0) {
            if ($orgId = $this->resolveOrganizationId($request)) {
                $this->refreshUsageFor($orgId, 'transactions_per_month');
            }
        }

        return $this->success([
            'results' => array_map(function ($r) {
                if (isset($r['transaction'])) {
                    $r['transaction'] = (new TransactionResource($r['transaction']))->resolve();
                }
                return $r;
            }, $results),
        ]);
    }

    /**
     * Delta-sync products: returns every product (and its inventory levels for
     * the user's accessible stores) updated since `?since=<ISO8601>`. When
     * `since` is omitted, returns the entire active catalog.
     */
    public function productChanges(\Illuminate\Http\Request $request): JsonResponse
    {
        $since = $request->query('since');
        $orgId = $request->user()->organization_id;
        $storeIds = $this->resolvedStoreIds($request);

        $query = Product::where('organization_id', $orgId);
        if ($since) {
            try {
                $cutoff = \Carbon\Carbon::parse($since);
                $query->where('updated_at', '>=', $cutoff);
            } catch (\Throwable $e) {
                return $this->error(__('pos.invalid_since_parameter'), 422);
            }
        }

        $products = $query->orderBy('updated_at')->limit((int) $request->query('limit', 500))->get();

        // Pull stock levels in a single query keyed by product_id+store_id.
        $stocks = \App\Domain\Inventory\Models\StockLevel::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('store_id', $storeIds)
            ->get(['product_id', 'store_id', 'quantity', 'reserved_quantity']);

        return $this->success([
            'products' => ProductResource::collection($products)->resolve(),
            'stocks' => $stocks->map(fn ($s) => [
                'product_id' => $s->product_id,
                'store_id' => $s->store_id,
                'quantity' => (float) $s->quantity,
                'reserved_quantity' => (float) ($s->reserved_quantity ?? 0),
            ])->values(),
            'server_time' => now()->toISOString(),
        ]);
    }

    /**
     * Apply a batch of inventory adjustments synced from offline registers.
     * Each adjustment carries a `reason` for the audit trail and writes to
     * `stock_movements` via the canonical StockService.
     */
    public function applyInventoryAdjustments(ApplyInventoryAdjustmentsRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $stockService = app(\App\Domain\Inventory\Services\StockService::class);

        $results = [];
        foreach ($request->validated('adjustments') as $adj) {
            try {
                $type = $adj['direction'] === 'in'
                    ? \App\Domain\Inventory\Enums\StockMovementType::AdjustmentIn
                    : \App\Domain\Inventory\Enums\StockMovementType::AdjustmentOut;

                $movement = $stockService->adjustStock(
                    storeId: $storeId,
                    productId: $adj['product_id'],
                    type: $type,
                    quantity: (float) $adj['quantity'],
                    unitCost: $adj['unit_cost'] ?? null,
                    referenceType: \App\Domain\Inventory\Enums\StockReferenceType::Adjustment,
                    referenceId: null,
                    reason: $adj['reason'],
                    performedBy: $request->user()->id,
                    idempotencyKey: $adj['client_uuid'] ?? null,
                );
                $results[] = [
                    'product_id' => $adj['product_id'],
                    'client_uuid' => $adj['client_uuid'] ?? null,
                    'status' => 'applied',
                    'movement_id' => $movement->id,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'product_id' => $adj['product_id'],
                    'client_uuid' => $adj['client_uuid'] ?? null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->success(['results' => $results]);
    }
}
