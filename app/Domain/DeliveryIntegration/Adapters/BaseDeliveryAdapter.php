<?php

namespace App\Domain\DeliveryIntegration\Adapters;

use App\Domain\DeliveryIntegration\Contracts\DeliveryPlatformInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseDeliveryAdapter implements DeliveryPlatformInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract protected function getBaseUrl(array $credentials): string;

    abstract protected function getAuthHeaders(array $credentials): array;

    abstract protected function mapMenuPayload(array $products): array;

    abstract protected function mapStatusPayload(string $status, array $extra = []): array;

    abstract protected function mapOperatingHoursPayload(array $hours): array;

    public function syncMenu(string $storeId, array $products, array $credentials): array
    {
        $baseUrl = $this->getBaseUrl($credentials);
        $headers = $this->getAuthHeaders($credentials);
        $payload = $this->mapMenuPayload($products);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$baseUrl}/menu/sync", $payload);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->json(),
                'items_synced' => count($products),
                'items_failed' => $response->successful() ? 0 : count($products),
            ];
        } catch (\Throwable $e) {
            Log::error("Delivery menu sync failed [{$this->getPlatformSlug()}]", [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items_synced' => 0,
                'items_failed' => count($products),
            ];
        }
    }

    public function pushOrderStatus(string $externalOrderId, string $status, array $credentials, array $extra = []): array
    {
        $baseUrl = $this->getBaseUrl($credentials);
        $headers = $this->getAuthHeaders($credentials);
        $payload = $this->mapStatusPayload($status, $extra);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->put("{$baseUrl}/orders/{$externalOrderId}/status", $payload);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error("Delivery status push failed [{$this->getPlatformSlug()}]", [
                'external_order_id' => $externalOrderId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function testConnection(array $credentials): array
    {
        $baseUrl = $this->getBaseUrl($credentials);
        $headers = $this->getAuthHeaders($credentials);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get("{$baseUrl}/ping");

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'message' => $response->successful() ? 'Connection successful' : 'Connection failed',
                'response_time_ms' => $response->handlerStats()['total_time'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function fetchOrders(array $credentials): array
    {
        $baseUrl = $this->getBaseUrl($credentials);
        $headers = $this->getAuthHeaders($credentials);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->get("{$baseUrl}/orders/pending");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'orders' => array_map(
                        fn (array $order) => $this->normalizeOrderPayload($order),
                        $response->json('orders', [])
                    ),
                ];
            }

            return ['success' => false, 'orders' => [], 'error' => $response->body()];
        } catch (\Throwable $e) {
            return ['success' => false, 'orders' => [], 'error' => $e->getMessage()];
        }
    }

    public function syncOperatingHours(array $hours, array $credentials): array
    {
        $baseUrl = $this->getBaseUrl($credentials);
        $headers = $this->getAuthHeaders($credentials);
        $payload = $this->mapOperatingHoursPayload($hours);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->put("{$baseUrl}/store/hours", $payload);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function toggleProductAvailability(string $externalProductId, bool $available, array $credentials): array
    {
        $baseUrl = $this->getBaseUrl($credentials);
        $headers = $this->getAuthHeaders($credentials);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->patch("{$baseUrl}/products/{$externalProductId}/availability", [
                    'available' => $available,
                ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $computed = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
