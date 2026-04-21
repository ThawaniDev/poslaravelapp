<?php

namespace App\Http\Controllers\Api;

use App\Domain\Payment\Requests\CreateInstallmentCheckoutRequest;
use App\Domain\Payment\Resources\InstallmentPaymentResource;
use App\Domain\Payment\Services\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallmentCheckoutController extends BaseApiController
{
    public function __construct(
        private readonly InstallmentService $installmentService,
    ) {}

    /**
     * Get providers available for checkout (filtered by amount/currency).
     */
    public function providers(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $storeId = $request->attributes->get('resolved_store_id');
        $providers = $this->installmentService->getCheckoutProviders(
            $storeId,
            (float) $request->input('amount'),
            $request->input('currency', 'SAR'),
        );

        return $this->success($providers);
    }

    /**
     * Pre-check Tamara eligibility for a customer.
     */
    public function tamaraPreCheck(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'customer_phone' => ['sometimes', 'nullable', 'string'],
            'country_code' => ['sometimes', 'string', 'size:2'],
        ]);

        $storeId = $request->attributes->get('resolved_store_id');

        try {
            $result = $this->installmentService->tamaraPreCheck($storeId, $request->all());
            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Create a checkout session with the selected provider.
     */
    public function createCheckout(CreateInstallmentCheckoutRequest $request): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');

        try {
            $payment = $this->installmentService->createCheckout($storeId, $request->validated());
            return $this->created(new InstallmentPaymentResource($payment), 'Checkout session created');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Confirm that a payment has been completed (called from Flutter after SDK/WebView success).
     */
    public function confirmPayment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'provider_data' => ['sometimes', 'array'],
        ]);

        try {
            $payment = $this->installmentService->confirmPayment($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->input('provider_data', []));
            return $this->success(new InstallmentPaymentResource($payment), 'Payment confirmed');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Payment not found');
        }
    }

    /**
     * Cancel a pending payment.
     */
    public function cancelPayment(Request $request, string $id): JsonResponse
    {
        try {
            $payment = $this->installmentService->cancelPayment($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);
            return $this->success(new InstallmentPaymentResource($payment), 'Payment cancelled');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Payment not found');
        }
    }

    /**
     * Report a failed payment.
     */
    public function failPayment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'error_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'error_message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->installmentService->failPayment(
                $id,
                $this->resolvedStoreId($request) ?? $request->user()->store_id,
                $request->input('error_code'),
                $request->input('error_message'),
            );
            return $this->success(new InstallmentPaymentResource($payment), 'Payment marked as failed');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Payment not found');
        }
    }

    /**
     * Get payment details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $payment = $this->installmentService->showPayment($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);
            return $this->success(new InstallmentPaymentResource($payment));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Payment not found');
        }
    }

    /**
     * List installment payments for the store.
     */
    public function history(Request $request): JsonResponse
    {
        $storeId = $request->attributes->get('resolved_store_id');

        $payments = $this->installmentService->listPayments($storeId, $request->all());
        $resource = InstallmentPaymentResource::collection($payments->getCollection());

        return $this->successPaginated($resource, $payments);
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Callbacks (server-side, no auth)
    // ═══════════════════════════════════════════════════════════════

    public function callbackSuccess(Request $request, string $provider): JsonResponse
    {
        // These are mainly used for server-side webhook/redirect verification.
        // The actual confirmation is typically done from Flutter via confirmPayment().
        return response()->json(['status' => 'ok', 'provider' => $provider, 'result' => 'success']);
    }

    public function callbackFailure(Request $request, string $provider): JsonResponse
    {
        return response()->json(['status' => 'ok', 'provider' => $provider, 'result' => 'failure']);
    }

    public function callbackCancel(Request $request, string $provider): JsonResponse
    {
        return response()->json(['status' => 'ok', 'provider' => $provider, 'result' => 'cancel']);
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        // Log webhook data for debugging / future processing
        \Illuminate\Support\Facades\Log::info("Installment webhook [{$provider}]", $request->all());
        return response()->json(['status' => 'received']);
    }
}
