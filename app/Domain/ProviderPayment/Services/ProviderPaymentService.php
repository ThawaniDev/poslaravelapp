<?php

namespace App\Domain\ProviderPayment\Services;

use App\Domain\ProviderPayment\Enums\PaymentPurpose;
use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProviderPaymentService
{
    public function __construct(
        private PayTabsService $payTabsService,
        private PaymentEmailService $emailService,
    ) {}

    // ─── Query ──────────────────────────────────────────────

    /**
     * List provider payments for an organization with filters.
     */
    public function listForOrganization(
        string $organizationId,
        array $filters = [],
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = ProviderPayment::with(['invoice', 'emailLogs'])
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a single provider payment by ID.
     */
    public function find(string $paymentId): ProviderPayment
    {
        return ProviderPayment::with(['invoice.invoiceLineItems', 'emailLogs', 'organization'])
            ->findOrFail($paymentId);
    }

    /**
     * Find a payment by its PayTabs transaction reference.
     */
    public function findByTranRef(string $tranRef): ?ProviderPayment
    {
        return ProviderPayment::where('tran_ref', $tranRef)->first();
    }

    /**
     * Find a payment by cart ID.
     */
    public function findByCartId(string $cartId): ?ProviderPayment
    {
        return ProviderPayment::where('cart_id', $cartId)->first();
    }

    // ─── Initiate Payment ───────────────────────────────────

    /**
     * Initiate a payment for a subscription, add-on, AI billing, etc.
     *
     * Returns array with redirect_url for PayTabs payment page.
     */
    public function initiatePayment(
        string $organizationId,
        PaymentPurpose $purpose,
        string $purposeLabel,
        float $amount,
        array $customerDetails,
        string $returnUrl,
        ?string $purposeReferenceId = null,
        ?string $invoiceId = null,
        ?string $initiatedBy = null,
        ?string $notes = null,
        float $taxRate = 15.0,
        string $currency = 'SAR',
    ): array {
        // ─── USD→SAR Conversion ─────────────────────────────
        $originalCurrency = null;
        $originalAmount = null;
        $exchangeRateUsed = null;

        if (strtoupper($currency) === 'USD') {
            $exchangeRate = (float) (SystemSetting::where('key', 'payment_usd_exchange_rate')->value('value') ?? 3.75);
            $originalCurrency = 'USD';
            $originalAmount = $amount;
            $exchangeRateUsed = $exchangeRate;
            $amount = round($amount * $exchangeRate, 2);
        }

        $taxAmount = round($amount * ($taxRate / 100), 2);
        $totalAmount = round($amount + $taxAmount, 2);
        $cartId = 'WP-' . strtoupper(Str::random(8)) . '-' . time();

        $callbackUrl = url('/api/v2/provider-payments/ipn');

        $payment = ProviderPayment::create([
            'organization_id' => $organizationId,
            'invoice_id' => $invoiceId,
            'purpose' => $purpose,
            'purpose_label' => $purposeLabel,
            'purpose_reference_id' => $purposeReferenceId,
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'currency' => 'SAR',
            'original_currency' => $originalCurrency,
            'original_amount' => $originalAmount,
            'exchange_rate_used' => $exchangeRateUsed,
            'gateway' => 'paytabs',
            'tran_type' => 'sale',
            'cart_id' => $cartId,
            'status' => ProviderPaymentStatus::Pending,
            'customer_details' => $customerDetails,
            'initiated_by' => $initiatedBy,
            'notes' => $notes,
        ]);

        $result = $this->payTabsService->createPaymentPage(
            payment: $payment,
            customerDetails: $customerDetails,
            returnUrl: $returnUrl,
            callbackUrl: $callbackUrl,
        );

        if (! $result['success']) {
            $payment->update([
                'status' => ProviderPaymentStatus::Failed,
                'response_message' => $result['error'],
            ]);
        }

        return [
            'payment_id' => $payment->id,
            'redirect_url' => $result['redirect_url'],
            'tran_ref' => $result['tran_ref'],
            'cart_id' => $cartId,
            'success' => $result['success'],
            'error' => $result['error'],
        ];
    }

    // ─── IPN Handler ────────────────────────────────────────

    /**
     * Handle IPN (Instant Payment Notification) from PayTabs.
     */
    public function handleIpn(array $data, string $rawBody, string $signature): bool
    {
        // Validate signature
        if (! $this->payTabsService->validateIpnSignature($rawBody, $signature)) {
            Log::channel('PayTabs')->warning('Invalid IPN signature', [
                'tran_ref' => $data['tran_ref'] ?? 'unknown',
            ]);
            return false;
        }

        $tranRef = $data['tran_ref'] ?? null;
        $cartId = $data['cart_id'] ?? null;
        $previousTranRef = $data['previous_tran_ref'] ?? null;
        $tranType = strtolower($data['tran_type'] ?? 'sale');

        if (! $tranRef) {
            Log::channel('PayTabs')->error('IPN missing tran_ref');
            return false;
        }

        // For refunds, look up by previous_tran_ref or cart_id
        if ($tranType === 'refund' && $previousTranRef) {
            $payment = $this->findByTranRef($previousTranRef) ?? $this->findByCartId($cartId);
        } else {
            $payment = $this->findByTranRef($tranRef) ?? $this->findByCartId($cartId);
        }

        if (! $payment) {
            Log::channel('PayTabs')->error('IPN payment not found', [
                'tran_ref' => $tranRef,
                'cart_id' => $cartId,
                'tran_type' => $tranType,
            ]);
            return false;
        }

        // Handle refund IPN for completed payments
        if ($tranType === 'refund' && $payment->status === ProviderPaymentStatus::Completed) {
            return $this->handleRefundIpn($payment, $data, $tranRef);
        }

        // If already processed terminal state, skip
        if ($payment->status->isTerminal()) {
            Log::channel('PayTabs')->info('IPN for already-terminal payment, skipping', [
                'payment_id' => $payment->id,
                'status' => $payment->status->value,
            ]);
            return true;
        }

        $paymentResult = $data['payment_result'] ?? [];
        $paymentInfo = $data['payment_info'] ?? [];
        $responseStatus = $paymentResult['response_status'] ?? $data['response_status'] ?? null;

        return DB::transaction(function () use ($payment, $data, $tranRef, $responseStatus, $paymentResult, $paymentInfo) {
            $payment->update([
                'tran_ref' => $payment->tran_ref ?? $tranRef,
                'ipn_received' => true,
                'ipn_received_at' => now(),
                'ipn_payload' => $data,
                'response_status' => $responseStatus,
                'response_code' => $paymentResult['response_code'] ?? $data['response_code'] ?? null,
                'response_message' => $paymentResult['response_message'] ?? $data['response_message'] ?? null,
                'card_type' => $paymentInfo['card_type'] ?? $data['card_type'] ?? null,
                'card_scheme' => $paymentInfo['card_scheme'] ?? null,
                'payment_description' => $paymentInfo['payment_description'] ?? $data['payment_description'] ?? null,
                'token' => $data['token'] ?? null,
                'gateway_response' => $data,
            ]);

            if ($responseStatus === 'A') {
                // Approved
                return $this->handlePaymentSuccess($payment);
            } elseif ($responseStatus === 'H') {
                // Hold on reject
                $payment->update(['status' => ProviderPaymentStatus::Processing]);
                Log::channel('PayTabs')->info('Payment on hold', ['payment_id' => $payment->id]);
                return true;
            } else {
                // Declined or Error
                return $this->handlePaymentFailure($payment);
            }
        });
    }

    // ─── Payment Return (from redirect) ─────────────────────

    /**
     * Handle the payment return URL from PayTabs redirect.
     * The user is redirected here after payment. We verify the transaction.
     */
    public function handlePaymentReturn(string $tranRef): ProviderPayment
    {
        $payment = $this->findByTranRef($tranRef);

        if (! $payment) {
            throw new \RuntimeException('Payment not found for transaction: ' . $tranRef);
        }

        // Query PayTabs to verify the transaction status
        $txnData = $this->payTabsService->queryTransaction($tranRef);

        if ($txnData) {
            $responseStatus = $txnData['payment_result']['response_status'] ?? null;

            if ($responseStatus === 'A' && ! $payment->isSuccessful()) {
                // If IPN hasn't arrived yet, process it now
                $this->handlePaymentSuccess($payment);
            }

            $payment->update([
                'gateway_response' => $txnData,
            ]);
        }

        return $payment->fresh(['invoice', 'emailLogs', 'organization']);
    }

    // ─── Refund ─────────────────────────────────────────────

    /**
     * Process a refund for a provider payment.
     */
    public function processRefund(string $paymentId, float $amount, string $reason): ProviderPayment
    {
        $payment = $this->find($paymentId);

        if (! $payment->canRefund()) {
            throw new \RuntimeException('This payment cannot be refunded.');
        }

        if ($amount > (float) $payment->total_amount) {
            throw new \RuntimeException('Refund amount exceeds payment total.');
        }

        $refundResult = $this->payTabsService->refund(
            $payment->tran_ref,
            $payment->cart_id,
            $amount,
            $reason,
        );

        if ($refundResult) {
            $refundStatus = $refundResult['payment_result']['response_status'] ?? null;

            $payment->update([
                'status' => $refundStatus === 'A' ? ProviderPaymentStatus::Refunded : $payment->status,
                'refund_amount' => $amount,
                'refund_tran_ref' => $refundResult['tran_ref'] ?? null,
                'refunded_at' => $refundStatus === 'A' ? now() : null,
                'refund_reason' => $reason,
            ]);

            // Update invoice if linked
            if ($payment->invoice_id) {
                $payment->invoice->update(['status' => 'refunded']);
            }

            // Send refund email
            if ($refundStatus === 'A') {
                $this->emailService->sendRefundConfirmation($payment);
            }
        } else {
            throw new \RuntimeException('Refund request to payment gateway failed.');
        }

        return $payment->fresh();
    }

    /**
     * Sync a payment's status from PayTabs gateway.
     * Useful for detecting refunds made from the PayTabs dashboard.
     */
    public function syncFromGateway(string $paymentId): ProviderPayment
    {
        $payment = $this->find($paymentId);

        if (! $payment->tran_ref) {
            throw new \RuntimeException('Payment has no transaction reference to query.');
        }

        $txnData = $this->payTabsService->queryTransaction($payment->tran_ref);

        if (! $txnData) {
            throw new \RuntimeException('Failed to query payment gateway.');
        }

        $payment->update(['gateway_response' => $txnData]);

        // Check if the transaction has been refunded by looking for refund info
        // PayTabs query returns the original transaction — we need to check transaction history
        // If the payment is completed but PayTabs shows a different status, update accordingly
        $responseStatus = $txnData['payment_result']['response_status'] ?? null;

        // If the gateway no longer shows 'A' (approved) and payment is completed, it may have been voided
        if ($responseStatus !== 'A' && $payment->status === ProviderPaymentStatus::Completed) {
            $payment->update([
                'status' => ProviderPaymentStatus::Voided,
                'response_status' => $responseStatus,
                'response_message' => $txnData['payment_result']['response_message'] ?? null,
            ]);
        }

        Log::channel('PayTabs')->info('Payment synced from gateway', [
            'payment_id' => $payment->id,
            'gateway_status' => $responseStatus,
        ]);

        return $payment->fresh();
    }

    // ─── Statistics ─────────────────────────────────────────

    /**
     * Get payment statistics for an organization.
     */
    public function getStatistics(string $organizationId): array
    {
        $payments = ProviderPayment::where('organization_id', $organizationId);

        return [
            'total_paid' => (clone $payments)->where('status', ProviderPaymentStatus::Completed)->sum('total_amount'),
            'total_pending' => (clone $payments)->where('status', ProviderPaymentStatus::Pending)->sum('total_amount'),
            'total_failed' => (clone $payments)->where('status', ProviderPaymentStatus::Failed)->count(),
            'total_refunded' => (clone $payments)->where('status', ProviderPaymentStatus::Refunded)->sum('refund_amount'),
            'total_payments' => (clone $payments)->count(),
            'emails_sent' => (clone $payments)->where('confirmation_email_sent', true)->count(),
            'invoices_generated' => (clone $payments)->where('invoice_generated', true)->count(),
        ];
    }

    // ─── Internal ───────────────────────────────────────────

    private function handlePaymentSuccess(ProviderPayment $payment): bool
    {
        $payment->update(['status' => ProviderPaymentStatus::Completed]);

        // Generate invoice if not already linked
        if (! $payment->invoice_id) {
            $this->generateInvoiceForPayment($payment);
        } else {
            // Mark existing invoice as paid
            $payment->invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_gateway' => 'paytabs',
                'gateway_tran_ref' => $payment->tran_ref,
                'provider_payment_id' => $payment->id,
            ]);
        }

        // Activate the purpose (subscription, addon, etc.)
        $this->activatePurpose($payment);

        // Send confirmation email
        $this->emailService->sendPaymentConfirmation($payment);

        // Send invoice email
        if ($payment->invoice_id) {
            $invoice = $payment->invoice->fresh();
            $this->emailService->sendInvoiceEmail($invoice, $payment);
        }

        Log::channel('PayTabs')->info('Payment completed successfully', [
            'payment_id' => $payment->id,
            'amount' => $payment->total_amount,
        ]);

        return true;
    }

    private function handlePaymentFailure(ProviderPayment $payment): bool
    {
        $payment->update(['status' => ProviderPaymentStatus::Failed]);

        // Update linked invoice
        if ($payment->invoice_id) {
            $payment->invoice->update(['status' => 'failed']);
        }

        // Send failure email
        $this->emailService->sendPaymentFailedEmail($payment);

        Log::channel('PayTabs')->info('Payment failed', [
            'payment_id' => $payment->id,
            'response' => $payment->response_message,
        ]);

        return true;
    }

    /**
     * Handle a refund IPN from PayTabs (dashboard or API refund).
     */
    private function handleRefundIpn(ProviderPayment $payment, array $data, string $refundTranRef): bool
    {
        $paymentResult = $data['payment_result'] ?? [];
        $responseStatus = $paymentResult['response_status'] ?? $data['response_status'] ?? null;
        $refundAmount = (float) ($data['cart_amount'] ?? $data['tran_total'] ?? $payment->total_amount);

        return DB::transaction(function () use ($payment, $data, $refundTranRef, $responseStatus, $refundAmount) {
            if ($responseStatus === 'A') {
                $payment->update([
                    'status' => ProviderPaymentStatus::Refunded,
                    'refund_amount' => $refundAmount,
                    'refund_tran_ref' => $refundTranRef,
                    'refunded_at' => now(),
                    'refund_reason' => 'Refunded via PayTabs dashboard',
                    'gateway_response' => $data,
                ]);

                // Update linked invoice
                if ($payment->invoice_id) {
                    $payment->invoice->update(['status' => 'refunded']);
                }

                // Send refund email
                $this->emailService->sendRefundConfirmation($payment);

                Log::channel('PayTabs')->info('Refund IPN processed successfully', [
                    'payment_id' => $payment->id,
                    'refund_tran_ref' => $refundTranRef,
                    'refund_amount' => $refundAmount,
                ]);
            } else {
                Log::channel('PayTabs')->warning('Refund IPN with non-approved status', [
                    'payment_id' => $payment->id,
                    'refund_tran_ref' => $refundTranRef,
                    'response_status' => $responseStatus,
                ]);
            }

            return true;
        });
    }

    private function generateInvoiceForPayment(ProviderPayment $payment): void
    {
        // Find the store subscription for this organization
        $subscription = StoreSubscription::where('organization_id', $payment->organization_id)->first();

        if (! $subscription) {
            Log::warning('No subscription found for invoice generation', [
                'payment_id' => $payment->id,
                'organization_id' => $payment->organization_id,
            ]);
            return;
        }

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $invoice = Invoice::create([
            'store_subscription_id' => $subscription->id,
            'invoice_number' => $invoiceNumber,
            'amount' => $payment->amount,
            'tax' => $payment->tax_amount,
            'total' => $payment->total_amount,
            'status' => 'paid',
            'due_date' => now(),
            'paid_at' => now(),
            'provider_payment_id' => $payment->id,
            'payment_gateway' => 'paytabs',
            'gateway_tran_ref' => $payment->tran_ref,
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => $payment->purpose_label ?? $payment->purpose->label(),
            'quantity' => 1,
            'unit_price' => $payment->amount,
            'total' => $payment->amount,
        ]);

        $payment->update([
            'invoice_id' => $invoice->id,
            'invoice_generated' => true,
            'invoice_generated_at' => now(),
        ]);
    }

    private function activatePurpose(ProviderPayment $payment): void
    {
        match ($payment->purpose) {
            PaymentPurpose::Subscription => $this->activateSubscription($payment),
            PaymentPurpose::PlanAddon => $this->activateAddon($payment),
            PaymentPurpose::MarketplacePurchase => $this->activateMarketplacePurchase($payment),
            default => null, // Other purposes don't need activation
        };
    }

    private function activateSubscription(ProviderPayment $payment): void
    {
        if (! $payment->purpose_reference_id) {
            return;
        }

        $subscription = StoreSubscription::find($payment->purpose_reference_id);

        if ($subscription && $subscription->status->value !== 'active') {
            $subscription->update(['status' => 'active']);
        }
    }

    private function activateAddon(ProviderPayment $payment): void
    {
        if (! $payment->purpose_reference_id) {
            return;
        }

        DB::table('store_add_ons')
            ->where('id', $payment->purpose_reference_id)
            ->update(['is_active' => true, 'activated_at' => now()]);
    }

    private function activateMarketplacePurchase(ProviderPayment $payment): void
    {
        app(\App\Domain\ContentOnboarding\Services\MarketplaceService::class)
            ->activatePurchaseByPayment($payment->id);
    }
}
