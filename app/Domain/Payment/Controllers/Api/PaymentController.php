<?php

namespace App\Domain\Payment\Controllers\Api;

use App\Domain\Payment\Requests\CloseCashSessionRequest;
use App\Domain\Payment\Requests\CreateCashEventRequest;
use App\Domain\Payment\Requests\CreateExpenseRequest;
use App\Domain\Payment\Requests\CreatePaymentRequest;
use App\Domain\Payment\Requests\IssueGiftCardRequest;
use App\Domain\Payment\Requests\OpenCashSessionRequest;
use App\Domain\Payment\Resources\CashEventResource;
use App\Domain\Payment\Resources\CashSessionResource;
use App\Domain\Payment\Resources\ExpenseResource;
use App\Domain\Payment\Resources\GiftCardResource;
use App\Domain\Payment\Resources\PaymentResource;
use App\Domain\Payment\Services\CashSessionService;
use App\Domain\Payment\Services\FinancialSummaryService;
use App\Domain\Payment\Services\GiftCardService;
use App\Domain\Payment\Services\PaymentService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseApiController
{
    public function __construct(
        private PaymentService $paymentService,
        private CashSessionService $cashSessionService,
        private GiftCardService $giftCardService,
        private FinancialSummaryService $financialSummaryService,
    ) {}

    // ─── Payments ───────────────────────────────────────────

    public function listPayments(Request $request): JsonResponse
    {
        $paginator = $this->paymentService->list(
            $this->resolvedStoreIds($request),
            $request->only(['method', 'transaction_id']),
            (int) $request->input('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = PaymentResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    public function createPayment(CreatePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated(), $request->user());

        return $this->created(new PaymentResource($payment));
    }

    // ─── Cash Sessions ──────────────────────────────────────

    public function listCashSessions(Request $request): JsonResponse
    {
        $paginator = $this->cashSessionService->list(
            $this->resolvedStoreIds($request),
            (int) $request->input('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CashSessionResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    public function openCashSession(OpenCashSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->cashSessionService->open($request->validated(), $request->user());
            return $this->created(new CashSessionResource($session));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function showCashSession(string $id): JsonResponse
    {
        try {
            $session = $this->cashSessionService->find($id);
            return $this->success(new CashSessionResource($session));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Cash session not found.');
        }
    }

    public function closeCashSession(string $id, CloseCashSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->cashSessionService->find($id);
            $session = $this->cashSessionService->close($session, $request->validated(), $request->user());
            return $this->success(new CashSessionResource($session));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Cash session not found.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Cash Events ────────────────────────────────────────

    public function createCashEvent(CreateCashEventRequest $request): JsonResponse
    {
        try {
            $session = $this->cashSessionService->find($request->input('cash_session_id'));
            $event = $this->cashSessionService->addCashEvent($session, $request->validated(), $request->user());
            return $this->created(new CashEventResource($event));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Cash session not found.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Expenses ───────────────────────────────────────────

    public function listExpenses(Request $request): JsonResponse
    {
        $paginator = $this->cashSessionService->listExpenses(
            $this->resolvedStoreIds($request),
            (int) $request->input('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = ExpenseResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    public function createExpense(CreateExpenseRequest $request): JsonResponse
    {
        $expense = $this->cashSessionService->addExpense($request->validated(), $request->user());

        return $this->created(new ExpenseResource($expense));
    }

    // ─── Gift Cards ─────────────────────────────────────────

    public function issueGiftCard(IssueGiftCardRequest $request): JsonResponse
    {
        $card = $this->giftCardService->issue($request->validated(), $request->user());

        return $this->created(new GiftCardResource($card));
    }

    public function checkGiftCardBalance(string $code): JsonResponse
    {
        try {
            $balance = $this->giftCardService->checkBalance($code);
            return $this->success($balance);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Gift card not found.');
        }
    }

    public function redeemGiftCard(string $code, Request $request): JsonResponse
    {
        $request->validate(['amount' => 'required|numeric|min:0.01']);

        try {
            $card = $this->giftCardService->redeem($code, (float) $request->input('amount'));
            return $this->success(new GiftCardResource($card));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Gift card not found.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Financial Summary ──────────────────────────────────

    public function dailySummary(Request $request): JsonResponse
    {
        $request->validate(['date' => 'sometimes|date_format:Y-m-d']);

        $date = $request->input('date', now()->toDateString());
        $storeIds = $this->resolvedStoreIds($request);

        $summary = $this->financialSummaryService->dailySummary($storeIds, $date);

        return $this->success($summary);
    }

    public function reconciliation(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d',
        ]);

        $startDate = $request->input('start_date', now()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $storeIds = $this->resolvedStoreIds($request);

        $data = $this->financialSummaryService->reconciliation($storeIds, $startDate, $endDate);

        return $this->success($data);
    }
}
