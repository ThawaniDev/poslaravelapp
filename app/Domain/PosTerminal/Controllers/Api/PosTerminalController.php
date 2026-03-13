<?php

namespace App\Domain\PosTerminal\Controllers\Api;

use App\Domain\PosTerminal\Requests\CloseSessionRequest;
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
            $request->user()->store_id,
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

    public function showSession(string $session): JsonResponse
    {
        $found = $this->sessionService->find($session);
        return $this->success(new PosSessionResource($found));
    }

    public function closeSession(CloseSessionRequest $request, string $session): JsonResponse
    {
        try {
            $found = $this->sessionService->find($session);
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
            $request->user()->store_id,
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

    public function showTransaction(string $transaction): JsonResponse
    {
        $found = $this->transactionService->find($transaction);
        return $this->success(new TransactionResource($found));
    }

    public function voidTransaction(Request $request, string $transaction): JsonResponse
    {
        try {
            $found = $this->transactionService->find($transaction);
            $voided = $this->transactionService->void($found, $request->user());
            return $this->success(new TransactionResource($voided));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Held Carts ──────────────────────────────────────────

    public function heldCarts(Request $request): JsonResponse
    {
        $carts = $this->heldCartService->list($request->user()->store_id);
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
}
