<?php

namespace App\Domain\Payment\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\InstallmentPaymentStatus;
use App\Domain\Payment\Enums\InstallmentProvider;
use App\Domain\Payment\Models\InstallmentPayment;
use App\Domain\Payment\Models\InstallmentProviderConfig;
use App\Domain\Payment\Models\StoreInstallmentConfig;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstallmentService
{
    // ═══════════════════════════════════════════════════════════════
    // Platform Admin — Provider Management
    // ═══════════════════════════════════════════════════════════════

    public function listProviders(): Collection
    {
        return InstallmentProviderConfig::orderBy('sort_order')->get();
    }

    public function showProvider(string $id): InstallmentProviderConfig
    {
        return InstallmentProviderConfig::findOrFail($id);
    }

    public function updateProvider(string $id, array $data): InstallmentProviderConfig
    {
        $provider = InstallmentProviderConfig::findOrFail($id);
        $provider->update($data);
        return $provider->fresh();
    }

    public function toggleProvider(string $id): InstallmentProviderConfig
    {
        $provider = InstallmentProviderConfig::findOrFail($id);
        $provider->update(['is_enabled' => !$provider->is_enabled]);
        return $provider->fresh();
    }

    public function setMaintenance(string $id, bool $underMaintenance, ?string $message = null, ?string $messageAr = null): InstallmentProviderConfig
    {
        $provider = InstallmentProviderConfig::findOrFail($id);
        $provider->update([
            'is_under_maintenance' => $underMaintenance,
            'maintenance_message' => $message,
            'maintenance_message_ar' => $messageAr,
        ]);
        return $provider->fresh();
    }

    // ═══════════════════════════════════════════════════════════════
    // Store Admin — Store Configuration
    // ═══════════════════════════════════════════════════════════════

    public function getAvailableProviders(string $storeId): Collection
    {
        $platformProviders = InstallmentProviderConfig::enabled()->orderBy('sort_order')->get();
        $storeConfigs = StoreInstallmentConfig::where('store_id', $storeId)->get()->keyBy('provider');

        return $platformProviders->map(function ($provider) use ($storeConfigs) {
            $storeConfig = $storeConfigs->get($provider->provider->value);
            return [
                'provider' => $provider,
                'store_config' => $storeConfig,
                'is_configured' => $storeConfig?->isFullyConfigured() ?? false,
                'is_store_enabled' => $storeConfig?->is_enabled ?? false,
            ];
        });
    }

    public function getStoreConfigs(string $storeId): Collection
    {
        return StoreInstallmentConfig::where('store_id', $storeId)
            ->with('providerConfig')
            ->get();
    }

    public function getStoreConfig(string $storeId, string $provider): ?StoreInstallmentConfig
    {
        return StoreInstallmentConfig::where('store_id', $storeId)
            ->where('provider', $provider)
            ->first();
    }

    public function upsertStoreConfig(string $storeId, string $provider, array $data): StoreInstallmentConfig
    {
        // Validate that the platform provider exists and is enabled
        $platformProvider = InstallmentProviderConfig::where('provider', $provider)->firstOrFail();
        if (!$platformProvider->is_enabled) {
            throw new \RuntimeException('This installment provider is not enabled by the platform.');
        }

        return StoreInstallmentConfig::updateOrCreate(
            ['store_id' => $storeId, 'provider' => $provider],
            $data
        );
    }

    public function toggleStoreConfig(string $storeId, string $provider): StoreInstallmentConfig
    {
        $config = StoreInstallmentConfig::where('store_id', $storeId)
            ->where('provider', $provider)
            ->firstOrFail();

        $config->update(['is_enabled' => !$config->is_enabled]);
        return $config->fresh();
    }

    public function deleteStoreConfig(string $storeId, string $provider): void
    {
        StoreInstallmentConfig::where('store_id', $storeId)
            ->where('provider', $provider)
            ->delete();
    }

    // ═══════════════════════════════════════════════════════════════
    // POS Checkout — Available Providers for a Store & Amount
    // ═══════════════════════════════════════════════════════════════

    public function getCheckoutProviders(string $storeId, float $amount, string $currency = 'SAR'): Collection
    {
        $platformProviders = InstallmentProviderConfig::available()->orderBy('sort_order')->get();
        $storeConfigs = StoreInstallmentConfig::where('store_id', $storeId)
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn ($c) => $c->provider->value);

        return $platformProviders->filter(function ($provider) use ($storeConfigs, $amount, $currency) {
            $storeConfig = $storeConfigs->get($provider->provider->value);
            if (!$storeConfig || !$storeConfig->isAvailable()) {
                return false;
            }
            return $provider->supportsAmount($amount) && $provider->supportsCurrency($currency);
        })->map(function ($provider) use ($storeConfigs, $amount) {
            $storeConfig = $storeConfigs->get($provider->provider->value);
            return [
                'provider' => $provider->provider->value,
                'name' => $provider->name,
                'name_ar' => $provider->name_ar,
                'logo_url' => $provider->logo_url,
                'description' => $provider->description,
                'description_ar' => $provider->description_ar,
                'installment_counts' => $provider->supported_installment_counts,
                'installment_amount' => round($amount / 4, 2), // Default preview
            ];
        })->values();
    }

    // ═══════════════════════════════════════════════════════════════
    // Checkout Session Creation — Provider-specific API Calls
    // ═══════════════════════════════════════════════════════════════

    public function createCheckout(string $storeId, array $data): InstallmentPayment
    {
        $provider = InstallmentProvider::from($data['provider']);
        $storeConfig = StoreInstallmentConfig::where('store_id', $storeId)
            ->where('provider', $provider->value)
            ->firstOrFail();

        if (!$storeConfig->isAvailable()) {
            throw new \RuntimeException('This installment provider is not configured or enabled for your store.');
        }

        $platformProvider = InstallmentProviderConfig::where('provider', $provider->value)->firstOrFail();
        if (!$platformProvider->isAvailable()) {
            throw new \RuntimeException('This installment provider is currently unavailable.');
        }

        // Create initial payment record
        $installmentPayment = InstallmentPayment::create([
            'store_id' => $storeId,
            'provider' => $provider->value,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'SAR',
            'installment_count' => $data['installment_count'] ?? null,
            'status' => InstallmentPaymentStatus::Pending,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'initiated_at' => now(),
        ]);

        try {
            $result = match ($provider) {
                InstallmentProvider::Tabby => $this->createTabbyCheckout($storeConfig, $platformProvider, $installmentPayment, $data),
                InstallmentProvider::Tamara => $this->createTamaraCheckout($storeConfig, $platformProvider, $installmentPayment, $data),
                InstallmentProvider::MisPay => $this->createMisPayCheckout($storeConfig, $platformProvider, $installmentPayment, $data),
                InstallmentProvider::Madfu => $this->createMadfuCheckout($storeConfig, $platformProvider, $installmentPayment, $data),
            };

            $installmentPayment->update([
                'status' => InstallmentPaymentStatus::CheckoutCreated,
                'checkout_url' => $result['checkout_url'] ?? null,
                'provider_order_id' => $result['provider_order_id'] ?? null,
                'provider_checkout_id' => $result['provider_checkout_id'] ?? null,
                'provider_payment_id' => $result['provider_payment_id'] ?? null,
                'provider_response' => $result,
            ]);

            return $installmentPayment->fresh();
        } catch (\Throwable $e) {
            Log::error("Installment checkout failed [{$provider->value}]", [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            $installmentPayment->markFailed('CHECKOUT_ERROR', $e->getMessage());
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Payment Confirmation & Webhooks
    // ═══════════════════════════════════════════════════════════════

    public function confirmPayment(string $installmentPaymentId, array $providerData = []): InstallmentPayment
    {
        $payment = InstallmentPayment::findOrFail($installmentPaymentId);

        if ($payment->status->isFinal()) {
            return $payment;
        }

        $payment->markCompleted($providerData);
        return $payment->fresh();
    }

    public function cancelPayment(string $installmentPaymentId): InstallmentPayment
    {
        $payment = InstallmentPayment::findOrFail($installmentPaymentId);

        if ($payment->status->isFinal()) {
            return $payment;
        }

        $payment->markCancelled();
        return $payment->fresh();
    }

    public function failPayment(string $installmentPaymentId, ?string $errorCode = null, ?string $errorMessage = null): InstallmentPayment
    {
        $payment = InstallmentPayment::findOrFail($installmentPaymentId);

        if ($payment->status->isFinal()) {
            return $payment;
        }

        $payment->markFailed($errorCode, $errorMessage);
        return $payment->fresh();
    }

    // ═══════════════════════════════════════════════════════════════
    // Payment History
    // ═══════════════════════════════════════════════════════════════

    public function listPayments(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InstallmentPayment::where('store_id', $storeId);

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function showPayment(string $id): InstallmentPayment
    {
        return InstallmentPayment::with(['transaction', 'payment'])->findOrFail($id);
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider-Specific Checkout Implementations
    // ═══════════════════════════════════════════════════════════════

    private function getBaseUrl(InstallmentProvider $provider, string $environment): string
    {
        return match ($provider) {
            InstallmentProvider::Tabby => $environment === 'production'
                ? 'https://api.tabby.ai/api/v2'
                : 'https://api.tabby.ai/api/v2',
            InstallmentProvider::Tamara => $environment === 'production'
                ? 'https://api.tamara.co'
                : 'https://api-sandbox.tamara.co',
            InstallmentProvider::MisPay => $environment === 'production'
                ? 'https://api.mispay.co/v1/api'
                : 'https://api-sandbox.mispay.dev/v1/api',
            InstallmentProvider::Madfu => $environment === 'production'
                ? 'https://api.madfu.com.sa'
                : 'https://api.staging.madfu.com.sa',
        };
    }

    // ─── Tabby ───────────────────────────────────────────────────

    private function createTabbyCheckout(
        StoreInstallmentConfig $storeConfig,
        InstallmentProviderConfig $platformConfig,
        InstallmentPayment $installmentPayment,
        array $data
    ): array {
        $baseUrl = $this->getBaseUrl(InstallmentProvider::Tabby, $storeConfig->environment);
        $secretKey = $storeConfig->getCredential('secret_key');
        $merchantCode = $storeConfig->getCredential('merchant_code');

        $items = array_map(fn ($item) => [
            'title' => $item['name'] ?? 'Product',
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => number_format($item['unit_price'] ?? 0, 2, '.', ''),
            'category' => $item['category'] ?? 'General',
            'reference_id' => $item['product_id'] ?? '',
        ], $data['items'] ?? []);

        $payload = [
            'payment' => [
                'amount' => number_format($data['amount'], 2, '.', ''),
                'currency' => $data['currency'] ?? 'SAR',
                'description' => $data['description'] ?? "Payment #{$installmentPayment->id}",
                'buyer' => [
                    'phone' => $data['customer_phone'] ?? '',
                    'email' => $data['customer_email'] ?? 'customer@store.com',
                    'name' => $data['customer_name'] ?? 'Customer',
                ],
                'buyer_history' => [
                    'registered_since' => $data['customer_registered_since'] ?? now()->subYear()->toIso8601String(),
                    'loyalty_level' => $data['customer_loyalty_level'] ?? 0,
                ],
                'order' => [
                    'reference_id' => $data['order_reference'] ?? $installmentPayment->id,
                    'items' => $items,
                    'tax_amount' => number_format($data['tax_amount'] ?? 0, 2, '.', ''),
                    'discount_amount' => number_format($data['discount_amount'] ?? 0, 2, '.', ''),
                ],
                'shipping_address' => [
                    'city' => $data['city'] ?? 'Riyadh',
                    'address' => $data['address'] ?? 'N/A',
                    'zip' => $data['zip'] ?? '12345',
                ],
            ],
            'lang' => $data['lang'] ?? 'ar',
            'merchant_code' => $merchantCode,
            'merchant_urls' => [
                'success' => $storeConfig->success_url ?? config('app.url') . '/api/v2/installments/callback/tabby/success',
                'cancel' => $storeConfig->cancel_url ?? config('app.url') . '/api/v2/installments/callback/tabby/cancel',
                'failure' => $storeConfig->failure_url ?? config('app.url') . '/api/v2/installments/callback/tabby/failure',
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$secretKey}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/checkout", $payload);

        if (!$response->successful()) {
            Log::error('Tabby checkout failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Tabby checkout failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        $responseData = $response->json();

        return [
            'checkout_url' => $responseData['configuration']['available_products']['installments'][0]['web_url'] ?? null,
            'provider_payment_id' => $responseData['payment']['id'] ?? null,
            'provider_checkout_id' => $responseData['id'] ?? null,
            'provider_order_id' => $responseData['payment']['id'] ?? null,
            'session_id' => $responseData['id'] ?? null,
            'available_products' => $responseData['configuration']['available_products'] ?? null,
            'raw_response' => $responseData,
        ];
    }

    // ─── Tamara ──────────────────────────────────────────────────

    private function createTamaraCheckout(
        StoreInstallmentConfig $storeConfig,
        InstallmentProviderConfig $platformConfig,
        InstallmentPayment $installmentPayment,
        array $data
    ): array {
        $baseUrl = $this->getBaseUrl(InstallmentProvider::Tamara, $storeConfig->environment);
        $apiToken = $storeConfig->getCredential('api_token');

        $items = array_map(fn ($item) => [
            'name' => $item['name'] ?? 'Product',
            'quantity' => $item['quantity'] ?? 1,
            'total_amount' => ['amount' => round(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 2), 'currency' => $data['currency'] ?? 'SAR'],
            'unit_price' => ['amount' => round($item['unit_price'] ?? 0, 2), 'currency' => $data['currency'] ?? 'SAR'],
            'sku' => $item['product_id'] ?? 'SKU',
            'type' => 'Digital',
            'reference_id' => $item['product_id'] ?? '',
            'tax_amount' => ['amount' => round($item['tax_amount'] ?? 0, 2), 'currency' => $data['currency'] ?? 'SAR'],
            'discount_amount' => ['amount' => round($item['discount_amount'] ?? 0, 2), 'currency' => $data['currency'] ?? 'SAR'],
        ], $data['items'] ?? []);

        $payload = [
            'total_amount' => ['amount' => round($data['amount'], 2), 'currency' => $data['currency'] ?? 'SAR'],
            'shipping_amount' => ['amount' => 0, 'currency' => $data['currency'] ?? 'SAR'],
            'tax_amount' => ['amount' => round($data['tax_amount'] ?? 0, 2), 'currency' => $data['currency'] ?? 'SAR'],
            'order_reference_id' => $data['order_reference'] ?? $installmentPayment->id,
            'order_number' => $data['order_reference'] ?? $installmentPayment->id,
            'discount' => [
                'amount' => ['amount' => round($data['discount_amount'] ?? 0, 2), 'currency' => $data['currency'] ?? 'SAR'],
                'name' => 'Discount',
            ],
            'items' => $items,
            'consumer' => [
                'email' => $data['customer_email'] ?? 'customer@store.com',
                'first_name' => explode(' ', $data['customer_name'] ?? 'Customer')[0],
                'last_name' => explode(' ', $data['customer_name'] ?? 'Customer')[1] ?? explode(' ', $data['customer_name'] ?? 'Customer')[0],
                'phone_number' => $data['customer_phone'] ?? '',
            ],
            'country_code' => $data['country_code'] ?? 'SA',
            'description' => $data['description'] ?? "Installment payment #{$installmentPayment->id}",
            'merchant_url' => [
                'success' => $storeConfig->success_url ?? config('app.url') . '/api/v2/installments/callback/tamara/success',
                'failure' => $storeConfig->failure_url ?? config('app.url') . '/api/v2/installments/callback/tamara/failure',
                'cancel' => $storeConfig->cancel_url ?? config('app.url') . '/api/v2/installments/callback/tamara/cancel',
                'notification' => config('app.url') . '/api/v2/installments/webhook/tamara',
            ],
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'instalments' => $data['installment_count'] ?? 3,
            'billing_address' => [
                'city' => $data['city'] ?? 'N/A',
                'country_code' => $data['country_code'] ?? 'SA',
                'first_name' => explode(' ', $data['customer_name'] ?? 'Customer')[0],
                'last_name' => explode(' ', $data['customer_name'] ?? 'Customer')[1] ?? explode(' ', $data['customer_name'] ?? 'Customer')[0],
                'line1' => $data['address'] ?? 'N/A',
                'phone_number' => $data['customer_phone'] ?? '',
            ],
            'shipping_address' => [
                'city' => $data['city'] ?? 'N/A',
                'country_code' => $data['country_code'] ?? 'SA',
                'first_name' => explode(' ', $data['customer_name'] ?? 'Customer')[0],
                'last_name' => explode(' ', $data['customer_name'] ?? 'Customer')[1] ?? explode(' ', $data['customer_name'] ?? 'Customer')[0],
                'line1' => $data['address'] ?? 'N/A',
                'phone_number' => $data['customer_phone'] ?? '',
            ],
            'platform' => 'POS',
            'is_mobile' => true,
            'locale' => $data['lang'] ?? 'ar',
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiToken}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/checkout", $payload);

        if (!$response->successful()) {
            Log::error('Tamara checkout failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Tamara checkout failed: ' . ($response->json('message') ?? $response->body()));
        }

        $responseData = $response->json();

        return [
            'checkout_url' => $responseData['checkout_url'] ?? null,
            'provider_order_id' => $responseData['order_id'] ?? null,
            'provider_checkout_id' => $responseData['checkout_id'] ?? null,
            'status' => $responseData['status'] ?? null,
            'raw_response' => $responseData,
        ];
    }

    // ─── Tamara Pre-Check ────────────────────────────────────────

    public function tamaraPreCheck(string $storeId, array $data): array
    {
        $storeConfig = StoreInstallmentConfig::where('store_id', $storeId)
            ->where('provider', InstallmentProvider::Tamara->value)
            ->firstOrFail();

        $baseUrl = $this->getBaseUrl(InstallmentProvider::Tamara, $storeConfig->environment);
        $apiToken = $storeConfig->getCredential('api_token');

        $payload = [
            'country' => $data['country_code'] ?? 'SA',
            'phone_number' => $data['customer_phone'] ?? '',
            'order_value' => [
                'amount' => round($data['amount'], 2),
                'currency' => $data['currency'] ?? 'SAR',
            ],
            'is_vip' => $data['is_vip'] ?? false,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiToken}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/checkout/payment-options-pre-check", $payload);

        return $response->json() ?? [];
    }

    // ─── MisPay ──────────────────────────────────────────────────

    private function createMisPayCheckout(
        StoreInstallmentConfig $storeConfig,
        InstallmentProviderConfig $platformConfig,
        InstallmentPayment $installmentPayment,
        array $data
    ): array {
        $baseUrl = $this->getBaseUrl(InstallmentProvider::MisPay, $storeConfig->environment);
        $appId = $storeConfig->getCredential('app_id');
        $appSecret = $storeConfig->getCredential('app_secret');

        // Step 1: Get token
        $tokenResponse = Http::withHeaders([
            'x-app-secret' => $appSecret,
            'x-app-id' => $appId,
            'Accept' => 'application/json',
        ])->get("{$baseUrl}/token");

        if (!$tokenResponse->successful() || !$tokenResponse->json('result.token')) {
            throw new \RuntimeException('MisPay token retrieval failed: ' . $tokenResponse->body());
        }

        $encryptedToken = $tokenResponse->json('result.token');
        $decryptedToken = $this->decryptMisPayToken($encryptedToken, $appSecret);

        if (!$decryptedToken) {
            throw new \RuntimeException('MisPay token decryption failed');
        }

        // Step 2: Create checkout
        $items = array_map(fn ($item) => [
            'name' => $item['name'] ?? 'Product',
            'sku' => $item['product_id'] ?? 'SKU',
            'price' => round($item['unit_price'] ?? 0, 2),
            'currency' => $data['currency'] ?? 'SAR',
            'quantity' => $item['quantity'] ?? 1,
        ], $data['items'] ?? []);

        $payload = [
            'amount' => round($data['amount'], 2),
            'currency' => $data['currency'] ?? 'SAR',
            'description' => $data['description'] ?? "Payment #{$installmentPayment->id}",
            'referenceId' => $data['order_reference'] ?? $installmentPayment->id,
            'customer' => [
                'name' => $data['customer_name'] ?? 'Customer',
                'email' => $data['customer_email'] ?? 'customer@store.com',
                'mobile' => $data['customer_phone'] ?? '',
            ],
            'items' => $items,
            'urls' => [
                'success' => $storeConfig->success_url ?? config('app.url') . '/api/v2/installments/callback/mispay/success',
                'failed' => $storeConfig->failure_url ?? config('app.url') . '/api/v2/installments/callback/mispay/failure',
                'cancel' => $storeConfig->cancel_url ?? config('app.url') . '/api/v2/installments/callback/mispay/cancel',
            ],
        ];

        $checkoutResponse = Http::withHeaders([
            'x-app-id' => $appId,
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$decryptedToken}",
        ])->post("{$baseUrl}/start-checkout", $payload);

        if (!$checkoutResponse->successful()) {
            Log::error('MisPay checkout failed', ['status' => $checkoutResponse->status(), 'body' => $checkoutResponse->body()]);
            throw new \RuntimeException('MisPay checkout failed: ' . $checkoutResponse->body());
        }

        $responseData = $checkoutResponse->json();

        return [
            'checkout_url' => $responseData['result']['url'] ?? null,
            'provider_order_id' => $responseData['result']['trackId'] ?? null,
            'raw_response' => $responseData,
        ];
    }

    private function decryptMisPayToken(string $encryptedToken, string $passphrase): ?string
    {
        try {
            $encryptedBytes = base64_decode($encryptedToken);
            $salt = substr($encryptedBytes, 0, 16);
            $iv = substr($encryptedBytes, 16, 12);
            $ciphertextWithTag = substr($encryptedBytes, 28);

            $key = hash_pbkdf2('sha256', $passphrase, $salt, 40000, 32, true);

            $tagLength = 16;
            $ciphertext = substr($ciphertextWithTag, 0, -$tagLength);
            $tag = substr($ciphertextWithTag, -$tagLength);

            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

            if ($decrypted === false) {
                return null;
            }

            $tokenData = json_decode($decrypted, true);
            return $tokenData['token'] ?? $decrypted;
        } catch (\Throwable $e) {
            Log::error('MisPay token decryption error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Madfu ───────────────────────────────────────────────────

    private function createMadfuCheckout(
        StoreInstallmentConfig $storeConfig,
        InstallmentProviderConfig $platformConfig,
        InstallmentPayment $installmentPayment,
        array $data
    ): array {
        $baseUrl = $this->getBaseUrl(InstallmentProvider::Madfu, $storeConfig->environment);
        $apiKey = $storeConfig->getCredential('api_key');
        $appCode = $storeConfig->getCredential('app_code');
        $authorization = $storeConfig->getCredential('authorization');
        $username = $storeConfig->getCredential('username');
        $password = $storeConfig->getCredential('password');

        $headers = [
            'APIKey' => $apiKey,
            'AppCode' => $appCode,
            'Authorization' => $authorization,
            'PlatformTypeId' => '5',
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];

        // Step 1: Init token
        $initResponse = Http::withHeaders($headers)
            ->post("{$baseUrl}/merchants/token/init", [
                'systemInfo' => 'pos',
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]);

        if (!$initResponse->successful() || !$initResponse->json('token')) {
            throw new \RuntimeException('Madfu token init failed: ' . $initResponse->body());
        }

        $initToken = $initResponse->json('token');

        // Step 2: Merchant login
        $loginHeaders = array_merge($headers, ['Token' => $initToken]);
        $loginResponse = Http::withHeaders($loginHeaders)
            ->post("{$baseUrl}/Merchants/sign-in", [
                'userName' => $username,
                'password' => $password,
            ]);

        if (!$loginResponse->successful() || !$loginResponse->json('token')) {
            throw new \RuntimeException('Madfu merchant login failed: ' . $loginResponse->body());
        }

        $merchantToken = $loginResponse->json('token');

        // Step 3: Create order
        $orderItems = array_map(fn ($item) => [
            'productName' => $item['name'] ?? 'Product',
            'productImage' => $item['image_url'] ?? '',
            'sku' => $item['product_id'] ?? 'SKU',
            'count' => $item['quantity'] ?? 1,
            'totalAmount' => round(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 2),
        ], $data['items'] ?? []);

        $taxAmount = $data['tax_amount'] ?? 0;
        $totalBeforeTax = $data['amount'] - $taxAmount;

        $orderPayload = [
            'order' => [
                'taxes' => $taxAmount,
                'actualValue' => round($totalBeforeTax, 2),
                'amount' => round($data['amount'], 2),
                'merchantReference' => $data['order_reference'] ?? $installmentPayment->id,
            ],
            'guestOrderData' => [
                'customerMobile' => ltrim($data['customer_phone'] ?? '', '+0'),
                'customerName' => $data['customer_name'] ?? 'Customer',
                'lang' => $data['lang'] ?? 'ar',
            ],
            'merchantUrls' => [
                'success' => $storeConfig->success_url ?? config('app.url') . '/api/v2/installments/callback/madfu/success',
                'failure' => $storeConfig->failure_url ?? config('app.url') . '/api/v2/installments/callback/madfu/failure',
                'cancel' => $storeConfig->cancel_url ?? config('app.url') . '/api/v2/installments/callback/madfu/cancel',
            ],
            'orderDetails' => $orderItems,
        ];

        $createHeaders = array_merge($headers, ['Token' => $merchantToken]);
        $createResponse = Http::withHeaders($createHeaders)
            ->post("{$baseUrl}/Merchants/Checkout/CreateOrder", $orderPayload);

        if (!$createResponse->successful() || !$createResponse->json('checkoutLink')) {
            Log::error('Madfu order creation failed', ['status' => $createResponse->status(), 'body' => $createResponse->body()]);
            throw new \RuntimeException('Madfu order creation failed: ' . $createResponse->body());
        }

        $responseData = $createResponse->json();

        return [
            'checkout_url' => $responseData['checkoutLink'] ?? null,
            'provider_order_id' => (string) ($responseData['orderId'] ?? ''),
            'provider_checkout_id' => $responseData['invoiceCode'] ?? null,
            'merchant_reference' => $responseData['merchantReference'] ?? null,
            'raw_response' => $responseData,
        ];
    }
}
