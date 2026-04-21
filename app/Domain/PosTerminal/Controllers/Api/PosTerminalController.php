<?php

namespace App\Domain\PosTerminal\Controllers\Api;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Resources\ProductResource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Resources\CustomerResource;
use App\Domain\PosTerminal\Requests\CloseSessionRequest;
use App\Domain\PosTerminal\Requests\CreateReturnRequest;
use App\Domain\PosTerminal\Requests\CreateTransactionRequest;
use App\Domain\PosTerminal\Requests\HoldCartRequest;
use App\Domain\PosTerminal\Requests\OpenSessionRequest;
use App\Domain\PosTerminal\Resources\HeldCartResource;
use App\Domain\PosTerminal\Resources\PosSessionResource;
use App\Domain\PosTerminal\Resources\TransactionResource;
use App\Domain\PosTerminal\Services\HeldCartService;
use App\Domain\PosTerminal\Services\PosSessionService;
use App\Domain\PosTerminal\Services\TransactionService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTerminalController extends BaseApiController
{
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

    public function voidTransaction(Request $request, string $transaction): JsonResponse
    {
        try {
            $found = $this->transactionService->find($this->resolvedStoreId($request) ?? $request->user()->store_id, $transaction);
            $voided = $this->transactionService->void($found, $request->user());
            return $this->success(new TransactionResource($voided));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
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

    public function exchangeTransaction(CreateTransactionRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['type'] = 'exchange';
            $transaction = $this->transactionService->create($data, $request->user());
            return $this->created(new TransactionResource($transaction));
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
}
