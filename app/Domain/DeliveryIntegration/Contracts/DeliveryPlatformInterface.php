<?php

namespace App\Domain\DeliveryIntegration\Contracts;

/**
 * Common interface for all delivery platform adapters.
 * Each platform (HungerStation, Jahez, Marsool, etc.) implements this interface.
 */
interface DeliveryPlatformInterface
{
    /** Push the product catalog to the delivery platform */
    public function syncMenu(string $storeId, array $products, array $credentials): array;

    /** Push an order status update to the delivery platform */
    public function pushOrderStatus(string $externalOrderId, string $status, array $credentials, array $extra = []): array;

    /** Test the API connection with the given credentials */
    public function testConnection(array $credentials): array;

    /** Fetch pending/new orders from the platform (polling fallback) */
    public function fetchOrders(array $credentials): array;

    /** Push store operating hours to the platform */
    public function syncOperatingHours(array $hours, array $credentials): array;

    /** Toggle product availability on the platform */
    public function toggleProductAvailability(string $externalProductId, bool $available, array $credentials): array;

    /** Get the platform identifier slug */
    public function getPlatformSlug(): string;

    /** Verify incoming webhook signature */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;

    /** Normalize a platform-specific order payload into our standard IngestOrderDTO format */
    public function normalizeOrderPayload(array $rawPayload): array;
}
