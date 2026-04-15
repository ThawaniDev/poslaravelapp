<?php

namespace App\Domain\ProviderPayment\Controllers\Api;

use App\Domain\ProviderPayment\Enums\PaymentPurpose;
use App\Domain\ProviderPayment\Resources\ProviderPaymentResource;
use App\Domain\ProviderPayment\Services\ProviderPaymentService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderPaymentController extends BaseApiController
{
    public function __construct(
        private ProviderPaymentService $paymentService,
    ) {}

    /**
     * List provider payments for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|string|in:pending,processing,completed,failed,refunded,voided',
            'purpose' => 'sometimes|string|in:subscription,plan_addon,ai_billing,hardware,implementation_fee,other',
            'from_date' => 'sometimes|date_format:Y-m-d',
            'to_date' => 'sometimes|date_format:Y-m-d',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $organizationId = $request->user()->organization_id;

        $paginator = $this->paymentService->listForOrganization(
            $organizationId,
            $request->only(['status', 'purpose', 'from_date', 'to_date']),
            (int) $request->input('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = ProviderPaymentResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    /**
     * Get a single provider payment.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->find($id);

            // Ensure the payment belongs to the organization
            if ($payment->organization_id !== $request->user()->organization_id) {
                return $this->notFound('Payment not found.');
            }

            return $this->success(new ProviderPaymentResource($payment));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Payment not found.');
        }
    }

    /**
     * Initiate a new payment (creates a PayTabs payment page).
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purpose' => 'required|string|in:subscription,plan_addon,ai_billing,hardware,implementation_fee,other',
            'purpose_label' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|in:SAR,USD',
            'purpose_reference_id' => 'sometimes|nullable|uuid',
            'invoice_id' => 'sometimes|nullable|uuid',
            'return_url' => 'sometimes|nullable|url|max:500',
            'notes' => 'sometimes|nullable|string|max:1000',
            'customer_name' => 'sometimes|string|max:200',
            'customer_email' => 'sometimes|email|max:200',
            'customer_phone' => 'sometimes|string|max:30',
            'customer_city' => 'sometimes|string|max:100',
            'customer_country' => 'sometimes|string|max:2',
        ]);

        $user = $request->user();
        $org = $user->organization;

        $customerDetails = [
            'name' => $validated['customer_name'] ?? $org?->name ?? $user->name ?? 'Provider',
            'email' => $validated['customer_email'] ?? $org?->email ?? $user->email ?? '',
            'phone' => $validated['customer_phone'] ?? $org?->phone ?? $user->phone ?? '',
            'street' => $org?->address ?? '',
            'city' => $validated['customer_city'] ?? $org?->city ?? 'Riyadh',
            'state' => $org?->state ?? 'RI',
            'country' => $validated['customer_country'] ?? 'SA',
            'zip' => $org?->zip ?? '00000',
            'ip' => $request->ip(),
        ];

        $returnUrl = $validated['return_url'] ?? url('/api/v2/provider-payments/return');

        $result = $this->paymentService->initiatePayment(
            organizationId: $user->organization_id,
            purpose: PaymentPurpose::from($validated['purpose']),
            purposeLabel: $validated['purpose_label'],
            amount: (float) $validated['amount'],
            customerDetails: $customerDetails,
            returnUrl: $returnUrl,
            purposeReferenceId: $validated['purpose_reference_id'] ?? null,
            invoiceId: $validated['invoice_id'] ?? null,
            initiatedBy: $user->id,
            notes: $validated['notes'] ?? null,
            currency: $validated['currency'] ?? 'SAR',
        );

        if (! $result['success']) {
            return $this->error($result['error'] ?? 'Failed to initiate payment.', 422);
        }

        $payment = $this->paymentService->find($result['payment_id']);

        $data = (new ProviderPaymentResource($payment))->resolve();
        $data['redirect_url'] = $result['redirect_url'];

        return $this->success($data, 'Payment initiated successfully.');
    }

    /**
     * Handle PayTabs IPN (webhook callback).
     * No auth required — validated by signature.
     */
    public function ipn(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('Signature', '');

        $data = $request->all();

        Log::channel('PayTabs')->info('IPN received', [
            'tran_ref' => $data['tran_ref'] ?? null,
            'cart_id' => $data['cart_id'] ?? null,
            'tran_type' => $data['tran_type'] ?? null,
        ]);

        $success = $this->paymentService->handleIpn($data, $rawBody, $signature);

        if (! $success) {
            return $this->error('IPN processing failed.', 400);
        }

        return $this->success(null, 'IPN processed successfully.');
    }

    /**
     * Handle the return URL after PayTabs redirect.
     */
    public function paymentReturn(Request $request): \Illuminate\Http\RedirectResponse|JsonResponse
    {
        $tranRef = $request->input('tranRef') ?? $request->input('tran_ref');
        $adminUrl = config('app.url') . '/admin/provider-payments';

        if (! $tranRef) {
            if ($request->expectsJson()) {
                return $this->error('Transaction reference is required.', 422);
            }

            return redirect($adminUrl);
        }

        try {
            $payment = $this->paymentService->handlePaymentReturn($tranRef);

            if ($request->expectsJson()) {
                return $this->success(new ProviderPaymentResource($payment));
            }

            return redirect($adminUrl . '/' . $payment->id);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return $this->error($e->getMessage(), 404);
            }

            return redirect($adminUrl);
        }
    }

    /**
     * Get payment statistics for the organization.
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        $stats = $this->paymentService->getStatistics($organizationId);

        return $this->success($stats);
    }

    /**
     * Retry sending the confirmation email for a payment.
     */
    public function resendEmail(string $id, Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->find($id);

            if ($payment->organization_id !== $request->user()->organization_id) {
                return $this->notFound('Payment not found.');
            }

            if (! $payment->isSuccessful()) {
                return $this->error('Can only resend emails for completed payments.', 422);
            }

            $emailService = app(\App\Domain\ProviderPayment\Services\PaymentEmailService::class);
            $sent = $emailService->sendPaymentConfirmation($payment);

            if ($sent) {
                return $this->success(null, 'Email sent successfully.');
            }

            return $this->error('Failed to send email.', 500);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Payment not found.');
        }
    }
}
