<?php

namespace App\Domain\DeliveryIntegration\Adapters;

class HungerStationAdapter extends BaseDeliveryAdapter
{
    public function getPlatformSlug(): string
    {
        return 'hungerstation';
    }

    protected function getBaseUrl(array $credentials): string
    {
        return $credentials['base_url'] ?? 'https://api.hungerstation.com/merchant/v1';
    }

    protected function getAuthHeaders(array $credentials): array
    {
        return [
            'Authorization' => 'Bearer ' . ($credentials['api_key'] ?? ''),
            'X-Merchant-Id' => $credentials['merchant_id'] ?? '',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function mapMenuPayload(array $products): array
    {
        return [
            'menu' => [
                'categories' => $this->groupByCategory($products),
            ],
        ];
    }

    protected function mapStatusPayload(string $status, array $extra = []): array
    {
        $statusMap = [
            'accepted' => 'ACCEPTED',
            'preparing' => 'PREPARING',
            'ready' => 'READY_FOR_PICKUP',
            'dispatched' => 'OUT_FOR_DELIVERY',
            'delivered' => 'DELIVERED',
            'rejected' => 'REJECTED',
            'cancelled' => 'CANCELLED',
        ];

        return [
            'status' => $statusMap[$status] ?? strtoupper($status),
            'reason' => $extra['reason'] ?? null,
            'estimated_preparation_time' => $extra['estimated_minutes'] ?? null,
        ];
    }

    protected function mapOperatingHoursPayload(array $hours): array
    {
        return [
            'operating_hours' => array_map(fn ($day) => [
                'day' => $day['day_of_week'],
                'open_time' => $day['open_time'],
                'close_time' => $day['close_time'],
                'is_closed' => $day['is_closed'] ?? false,
            ], $hours),
        ];
    }

    public function normalizeOrderPayload(array $rawPayload): array
    {
        return [
            'external_order_id' => $rawPayload['order_id'] ?? $rawPayload['id'] ?? '',
            'customer_name' => $rawPayload['customer']['name'] ?? 'Unknown',
            'customer_phone' => $rawPayload['customer']['phone'] ?? null,
            'delivery_address' => $rawPayload['delivery_address']['full_address'] ?? null,
            'subtotal' => (float) ($rawPayload['subtotal'] ?? 0),
            'delivery_fee' => (float) ($rawPayload['delivery_fee'] ?? 0),
            'total' => (float) ($rawPayload['total_amount'] ?? 0),
            'commission' => (float) ($rawPayload['commission'] ?? 0),
            'commission_percent' => (float) ($rawPayload['commission_rate'] ?? 0),
            'items' => array_map(fn ($item) => [
                'name' => $item['name'] ?? '',
                'name_ar' => $item['name_ar'] ?? $item['name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['price'] ?? 0),
                'total_price' => (float) ($item['total'] ?? $item['price'] ?? 0),
                'notes' => $item['notes'] ?? null,
            ], $rawPayload['items'] ?? []),
            'notes' => $rawPayload['special_instructions'] ?? null,
            'estimated_prep_minutes' => $rawPayload['estimated_prep_time'] ?? null,
        ];
    }

    private function groupByCategory(array $products): array
    {
        $grouped = [];
        foreach ($products as $product) {
            $categoryName = $product['category_name'] ?? 'General';
            $grouped[$categoryName][] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'name_ar' => $product['name_ar'] ?? $product['name'],
                'description' => $product['description'] ?? '',
                'price' => (float) $product['price'],
                'image_url' => $product['image_url'] ?? null,
                'is_available' => $product['is_available'] ?? true,
            ];
        }

        return array_map(fn ($name, $items) => [
            'name' => $name,
            'items' => $items,
        ], array_keys($grouped), array_values($grouped));
    }
}
