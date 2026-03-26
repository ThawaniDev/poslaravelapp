<?php

namespace App\Domain\DeliveryIntegration\Adapters;

class GenericDeliveryAdapter extends BaseDeliveryAdapter
{
    private string $slug;

    public function __construct(array $config, string $slug = 'generic')
    {
        parent::__construct($config);
        $this->slug = $slug;
    }

    public function getPlatformSlug(): string
    {
        return $this->slug;
    }

    protected function getBaseUrl(array $credentials): string
    {
        return $credentials['base_url'] ?? '';
    }

    protected function getAuthHeaders(array $credentials): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $credentials['api_key'];
        }

        if (! empty($credentials['merchant_id'])) {
            $headers['X-Merchant-Id'] = $credentials['merchant_id'];
        }

        return $headers;
    }

    protected function mapMenuPayload(array $products): array
    {
        return [
            'products' => array_map(fn ($p) => [
                'id' => (string) $p['id'],
                'name' => $p['name'],
                'name_ar' => $p['name_ar'] ?? $p['name'],
                'description' => $p['description'] ?? '',
                'price' => (float) $p['price'],
                'image_url' => $p['image_url'] ?? null,
                'available' => $p['is_available'] ?? true,
                'category' => $p['category_name'] ?? 'General',
            ], $products),
        ];
    }

    protected function mapStatusPayload(string $status, array $extra = []): array
    {
        return [
            'status' => $status,
            'reason' => $extra['reason'] ?? null,
            'estimated_minutes' => $extra['estimated_minutes'] ?? null,
        ];
    }

    protected function mapOperatingHoursPayload(array $hours): array
    {
        return ['operating_hours' => $hours];
    }

    public function normalizeOrderPayload(array $rawPayload): array
    {
        return [
            'external_order_id' => (string) ($rawPayload['order_id'] ?? $rawPayload['id'] ?? $rawPayload['external_id'] ?? ''),
            'customer_name' => $rawPayload['customer_name'] ?? $rawPayload['customer']['name'] ?? 'Unknown',
            'customer_phone' => $rawPayload['customer_phone'] ?? $rawPayload['customer']['phone'] ?? null,
            'delivery_address' => $rawPayload['delivery_address'] ?? $rawPayload['address'] ?? null,
            'subtotal' => (float) ($rawPayload['subtotal'] ?? 0),
            'delivery_fee' => (float) ($rawPayload['delivery_fee'] ?? 0),
            'total' => (float) ($rawPayload['total'] ?? $rawPayload['total_amount'] ?? 0),
            'commission' => (float) ($rawPayload['commission'] ?? 0),
            'commission_percent' => (float) ($rawPayload['commission_percent'] ?? 0),
            'items' => array_map(fn ($item) => [
                'name' => $item['name'] ?? '',
                'name_ar' => $item['name_ar'] ?? $item['name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? $item['total'] ?? 0),
                'notes' => $item['notes'] ?? null,
            ], $rawPayload['items'] ?? []),
            'notes' => $rawPayload['notes'] ?? null,
            'estimated_prep_minutes' => $rawPayload['estimated_prep_minutes'] ?? $rawPayload['prep_time'] ?? null,
        ];
    }
}
