<?php

namespace App\Domain\ProviderPayment\Services;

use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use Illuminate\Support\Facades\Log;
use Paytabscom\Laravel_paytabs\Facades\paypage;

class PayTabsService
{
    /**
     * Create a PayTabs payment page for the given provider payment.
     *
     * @return array{redirect_url: string|null, tran_ref: string|null, success: bool, error: string|null}
     */
    public function createPaymentPage(
        ProviderPayment $payment,
        array $customerDetails,
        string $returnUrl,
        string $callbackUrl,
        string $language = 'ar',
    ): array {
        try {
            $response = paypage::sendPaymentCode('all')
                ->sendTransaction('sale', 'ecom')
                ->sendCart(
                    $payment->cart_id,
                    (float) $payment->total_amount,
                    $payment->purpose_label ?? $payment->purpose->label(),
                )
                ->sendCustomerDetails(
                    $customerDetails['name'] ?? 'Provider',
                    $customerDetails['email'] ?? '',
                    $customerDetails['phone'] ?? '',
                    $customerDetails['street'] ?? '',
                    $customerDetails['city'] ?? '',
                    $customerDetails['state'] ?? '',
                    $customerDetails['country'] ?? 'SA',
                    $customerDetails['zip'] ?? '00000',
                    $customerDetails['ip'] ?? request()->ip(),
                )
                ->shipping_same_billing()
                ->sendURLs($returnUrl, $callbackUrl)
                ->sendLanguage($language)
                ->sendUserDefined([
                    'udf1' => $payment->id,
                    'udf2' => $payment->organization_id,
                    'udf3' => $payment->purpose->value,
                ])
                ->create_pay_page();

            if (is_object($response) || is_array($response)) {
                $data = is_object($response) ? json_decode(json_encode($response), true) : $response;

                $tranRef = $data['tran_ref'] ?? null;
                $redirectUrl = $data['redirect_url'] ?? null;

                if ($tranRef) {
                    $payment->update([
                        'tran_ref' => $tranRef,
                        'status' => ProviderPaymentStatus::Processing,
                        'gateway_response' => $data,
                    ]);
                }

                Log::channel('PayTabs')->info('Payment page created', [
                    'payment_id' => $payment->id,
                    'tran_ref' => $tranRef,
                ]);

                return [
                    'success' => (bool) $redirectUrl,
                    'redirect_url' => $redirectUrl,
                    'tran_ref' => $tranRef,
                    'error' => null,
                ];
            }

            Log::channel('PayTabs')->error('Unexpected response creating payment page', [
                'payment_id' => $payment->id,
                'response' => $response,
            ]);

            return [
                'success' => false,
                'redirect_url' => null,
                'tran_ref' => null,
                'error' => 'Unexpected response from payment gateway.',
            ];
        } catch (\Throwable $e) {
            Log::channel('PayTabs')->error('Failed to create payment page', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'redirect_url' => null,
                'tran_ref' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Query a transaction from PayTabs.
     */
    public function queryTransaction(string $tranRef): ?array
    {
        try {
            $result = paypage::queryTransaction($tranRef);
            $data = is_object($result) ? json_decode(json_encode($result), true) : $result;

            Log::channel('PayTabs')->info('Transaction queried', [
                'tran_ref' => $tranRef,
                'status' => $data['payment_result']['response_status'] ?? 'unknown',
            ]);

            return $data;
        } catch (\Throwable $e) {
            Log::channel('PayTabs')->error('Failed to query transaction', [
                'tran_ref' => $tranRef,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process a refund via PayTabs.
     */
    public function refund(string $tranRef, string $orderId, float $amount, string $reason): ?array
    {
        try {
            $result = paypage::refund($tranRef, $orderId, $amount, $reason);
            $data = is_object($result) ? json_decode(json_encode($result), true) : $result;

            Log::channel('PayTabs')->info('Refund processed', [
                'tran_ref' => $tranRef,
                'amount' => $amount,
                'result' => $data,
            ]);

            return $data;
        } catch (\Throwable $e) {
            Log::channel('PayTabs')->error('Refund failed', [
                'tran_ref' => $tranRef,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate an IPN signature from PayTabs.
     */
    public function validateIpnSignature(string $requestBody, string $signature): bool
    {
        $serverKey = config('paytabs.server_key');

        if (! $serverKey || ! $signature) {
            return false;
        }

        $computed = hash_hmac('sha256', $requestBody, $serverKey);

        return hash_equals($computed, $signature);
    }
}
