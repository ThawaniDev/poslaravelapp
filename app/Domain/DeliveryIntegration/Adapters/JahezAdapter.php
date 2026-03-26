<?php

namespace App\Domain\DeliveryIntegration\Adapters;

class JahezAdapter extends BaseDeliveryAdapter
{
    public function getPlatformSlug(): string
    {
        return 'jahez';
    }

    protected function getBaseUrl(array $credentials): string
    {
        return $credentials['base_url'] ?? 'https://api.jahez.net/merchant/v2';
    }

    protected function getAuthHeaders(array $credentials): array
    {
        return [
            'Authorization' => 'Bearer ' . ($credentials['api_key'] ?? ''),
            'X-Restaurant-Id' => $credentials['restaurant_id'] ?? '',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function mapMenuPayload(array $products): array
    {
        return [
            'items' => array_map(fn ($p) => [
                'external_id' => (string) $p['id'],
                'name_en' => $p['name'],
                'name_ar' => $p['name_ar'] ?? $p['name'],
                'description_en' => $p['description'] ?? '',
                'description_ar' => $p['description_ar'] ?? $p['description'] ?? '',
                'price' => (float) $p['price'],
                'image' => $p['image_url'] ?? null,
                'available' => $p['is_available'] ?? true,
                'category' => $p['category_name'] ?? 'General',
            ], $products),
        ];
    }

    protected function mapStatusPayload(string $status, array $extra = []): array
    {
        $statusMap = [
            'accepted' => 'accepted',
            'preparing' => 'in_kitchen',
            'ready' => 'ready',
            'dispatched' => 'picked_up',
            'delivered' => 'delivered',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
        ];

        return [
            'order_status' => $statusMap[$status] ?? $status,
            'rejection_reason' => $extra['reason'] ?? null,
            'preparation_time_minutes' => $extra['estimated_minutes'] ?? null,
        ];
    }

    protected function mapOperatingHoursPayload(array $hours): array
    {
        return [
            'schedule' => array_map(fn ($day) => [
                'day_of_week' => strtolower($day['day_of_week']),
                'opens_at' => $day['open_time'],
                'closes_at' => $day['close_time'],
                'closed' => $day['is_closed'] ?? false,
            ], $hours),
        ];
    }

    public function normalizeOrderPayload(array $rawPayload): array
    {
        return [
            'external_order_id' => (string) ($rawPayload['order_id'] ?? $rawPayload['id'] ?? ''),
            'customer_name' => $rawPayload['customer_name'] ?? $rawPayload['customer']['name'] ?? 'Unknown',
            'customer_phone' => $rawPayload['customer_phone'] ?? $rawPayload['customer']['mobile'] ?? null,
            'delivery_address' => $rawPayload['address'] ?? $rawPayload['delivery_address'] ?? null,
            'subtotal' => (float) ($rawPayload['sub_total'] ?? $rawPayload['subtotal'] ?? 0),
            'delivery_fee' => (float) ($rawPayload['delivery_fee'] ?? 0),
            'total' => (float) ($rawPayload['total'] ?? $rawPayload['total_amount'] ?? 0),
            'commission' => (float) ($rawPayload['jahez_commission'] ?? 0),
            'commission_percent' => (float) ($rawPayload['commission_percentage'] ?? 0),
            'items' => array_map(fn ($item) => [
                'name' => $item['name_en'] ?? $item['name'] ?? '',
                'name_ar' => $item['name_ar'] ?? $item['name'] ?? '',
                'quantity' => (int) ($item['qty'] ?? $item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? $item['price'] ?? 0),
                'notes' => $item['special_request'] ?? $item['notes'] ?? null,
            ], $rawPayload['items'] ?? $rawPayload['order_items'] ?? []),
            'notes' => $rawPayload['notes'] ?? $rawPayload['special_instructions'] ?? null,
            'estimated_prep_minutes' => $rawPayload['preparation_time'] ?? null,
        ];
    }
}
