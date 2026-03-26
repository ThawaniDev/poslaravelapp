<?php

namespace App\Domain\DeliveryIntegration\DTOs;

readonly class IngestOrderDTO
{
    public function __construct(
        public string $storeId,
        public string $platform,
        public string $externalOrderId,
        public string $customerName,
        public ?string $customerPhone,
        public ?string $deliveryAddress,
        public float $subtotal,
        public float $deliveryFee,
        public float $totalAmount,
        public float $commissionAmount,
        public ?float $commissionPercent,
        public array $items,
        public ?string $notes = null,
        public ?int $estimatedPrepMinutes = null,
        public array $rawPayload = [],
    ) {}

    public static function fromWebhookPayload(string $storeId, string $platform, array $payload): self
    {
        return new self(
            storeId: $storeId,
            platform: $platform,
            externalOrderId: $payload['external_order_id'] ?? $payload['order_id'] ?? '',
            customerName: $payload['customer_name'] ?? $payload['customer']['name'] ?? 'Unknown',
            customerPhone: $payload['customer_phone'] ?? $payload['customer']['phone'] ?? null,
            deliveryAddress: $payload['delivery_address'] ?? $payload['address']['full'] ?? null,
            subtotal: (float) ($payload['subtotal'] ?? 0),
            deliveryFee: (float) ($payload['delivery_fee'] ?? 0),
            totalAmount: (float) ($payload['total'] ?? $payload['total_amount'] ?? 0),
            commissionAmount: (float) ($payload['commission'] ?? $payload['commission_amount'] ?? 0),
            commissionPercent: isset($payload['commission_percent']) ? (float) $payload['commission_percent'] : null,
            items: $payload['items'] ?? [],
            notes: $payload['notes'] ?? $payload['special_instructions'] ?? null,
            estimatedPrepMinutes: $payload['estimated_prep_minutes'] ?? $payload['prep_time'] ?? null,
            rawPayload: $payload,
        );
    }
}
