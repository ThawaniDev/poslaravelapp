<?php

namespace App\Domain\DeliveryIntegration\Adapters;

class MarsoolAdapter extends BaseDeliveryAdapter
{
    public function getPlatformSlug(): string
    {
        return 'marsool';
    }

    protected function getBaseUrl(array $credentials): string
    {
        return $credentials['base_url'] ?? 'https://api.marsool.sa/merchant/v1';
    }

    protected function getAuthHeaders(array $credentials): array
    {
        return [
            'X-API-Key' => $credentials['api_key'] ?? '',
            'X-Merchant-Token' => $credentials['merchant_token'] ?? '',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function mapMenuPayload(array $products): array
    {
        return [
            'catalog' => [
                'products' => array_map(fn ($p) => [
                    'sku' => (string) $p['id'],
                    'title' => $p['name'],
                    'title_arabic' => $p['name_ar'] ?? $p['name'],
                    'description' => $p['description'] ?? '',
                    'price_sar' => (float) $p['price'],
                    'photo_url' => $p['image_url'] ?? null,
                    'in_stock' => $p['is_available'] ?? true,
                    'category' => $p['category_name'] ?? 'Default',
                ], $products),
            ],
        ];
    }

    protected function mapStatusPayload(string $status, array $extra = []): array
    {
        $statusMap = [
            'accepted' => 'CONFIRMED',
            'preparing' => 'PREPARING',
            'ready' => 'READY',
            'dispatched' => 'DISPATCHED',
            'delivered' => 'COMPLETED',
            'rejected' => 'DECLINED',
            'cancelled' => 'CANCELLED',
        ];

        return [
            'new_status' => $statusMap[$status] ?? strtoupper($status),
            'decline_reason' => $extra['reason'] ?? null,
            'prep_time' => $extra['estimated_minutes'] ?? null,
        ];
    }

    protected function mapOperatingHoursPayload(array $hours): array
    {
        return [
            'availability' => array_map(fn ($day) => [
                'weekday' => $day['day_of_week'],
                'start' => $day['open_time'],
                'end' => $day['close_time'],
                'available' => ! ($day['is_closed'] ?? false),
            ], $hours),
        ];
    }

    public function normalizeOrderPayload(array $rawPayload): array
    {
        return [
            'external_order_id' => (string) ($rawPayload['reference'] ?? $rawPayload['order_ref'] ?? ''),
            'customer_name' => $rawPayload['recipient']['name'] ?? 'Unknown',
            'customer_phone' => $rawPayload['recipient']['phone'] ?? null,
            'delivery_address' => $rawPayload['drop_off']['address'] ?? null,
            'subtotal' => (float) ($rawPayload['pricing']['subtotal'] ?? 0),
            'delivery_fee' => (float) ($rawPayload['pricing']['delivery_fee'] ?? 0),
            'total' => (float) ($rawPayload['pricing']['total'] ?? 0),
            'commission' => (float) ($rawPayload['pricing']['service_fee'] ?? 0),
            'commission_percent' => (float) ($rawPayload['pricing']['service_fee_percent'] ?? 0),
            'items' => array_map(fn ($item) => [
                'name' => $item['title'] ?? '',
                'name_ar' => $item['title_arabic'] ?? $item['title'] ?? '',
                'quantity' => (int) ($item['count'] ?? 1),
                'unit_price' => (float) ($item['price_sar'] ?? 0),
                'total_price' => (float) (($item['price_sar'] ?? 0) * ($item['count'] ?? 1)),
                'notes' => $item['remark'] ?? null,
            ], $rawPayload['items'] ?? []),
            'notes' => $rawPayload['merchant_notes'] ?? null,
            'estimated_prep_minutes' => $rawPayload['expected_prep_minutes'] ?? null,
        ];
    }
}
