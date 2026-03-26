<?php

namespace Tests\Unit\Domain\DeliveryIntegration;

use App\Domain\DeliveryIntegration\Adapters\GenericDeliveryAdapter;
use App\Domain\DeliveryIntegration\Adapters\HungerStationAdapter;
use App\Domain\DeliveryIntegration\Adapters\JahezAdapter;
use App\Domain\DeliveryIntegration\Adapters\MarsoolAdapter;
use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\DTOs\PushOrderStatusDTO;
use App\Domain\DeliveryIntegration\DTOs\SavePlatformConfigDTO;
use App\Domain\DeliveryIntegration\Enums\DeliveryConfigPlatform;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Enums\MenuSyncTrigger;
use App\Domain\DeliveryIntegration\Enums\WebhookEventType;
use Tests\TestCase;

class DeliveryUnitTest extends TestCase
{
    // ─── DeliveryOrderStatus Enum ─────────────────────────────────

    public function test_delivery_order_status_labels(): void
    {
        $this->assertEquals('Pending', DeliveryOrderStatus::Pending->label());
        $this->assertEquals('Accepted', DeliveryOrderStatus::Accepted->label());
        $this->assertEquals('Preparing', DeliveryOrderStatus::Preparing->label());
        $this->assertEquals('Ready', DeliveryOrderStatus::Ready->label());
        $this->assertEquals('Dispatched', DeliveryOrderStatus::Dispatched->label());
        $this->assertEquals('Delivered', DeliveryOrderStatus::Delivered->label());
        $this->assertEquals('Rejected', DeliveryOrderStatus::Rejected->label());
        $this->assertEquals('Cancelled', DeliveryOrderStatus::Cancelled->label());
        $this->assertEquals('Failed', DeliveryOrderStatus::Failed->label());
    }

    public function test_delivery_order_status_terminal_states(): void
    {
        $this->assertFalse(DeliveryOrderStatus::Pending->isTerminal());
        $this->assertFalse(DeliveryOrderStatus::Accepted->isTerminal());
        $this->assertFalse(DeliveryOrderStatus::Preparing->isTerminal());
        $this->assertTrue(DeliveryOrderStatus::Delivered->isTerminal());
        $this->assertTrue(DeliveryOrderStatus::Rejected->isTerminal());
        $this->assertTrue(DeliveryOrderStatus::Cancelled->isTerminal());
        $this->assertTrue(DeliveryOrderStatus::Failed->isTerminal());
    }

    public function test_delivery_order_status_transitions(): void
    {
        $this->assertTrue(DeliveryOrderStatus::Pending->canTransitionTo(DeliveryOrderStatus::Accepted));
        $this->assertTrue(DeliveryOrderStatus::Pending->canTransitionTo(DeliveryOrderStatus::Rejected));
        $this->assertFalse(DeliveryOrderStatus::Pending->canTransitionTo(DeliveryOrderStatus::Delivered));
        $this->assertFalse(DeliveryOrderStatus::Delivered->canTransitionTo(DeliveryOrderStatus::Pending));
    }

    public function test_delivery_order_status_colors(): void
    {
        $this->assertEquals('warning', DeliveryOrderStatus::Pending->color());
        $this->assertEquals('success', DeliveryOrderStatus::Delivered->color());
        $this->assertEquals('danger', DeliveryOrderStatus::Rejected->color());
    }

    // ─── DeliveryConfigPlatform Enum ──────────────────────────────

    public function test_delivery_config_platform_labels(): void
    {
        $this->assertEquals('HungerStation', DeliveryConfigPlatform::Hungerstation->label());
        $this->assertEquals('Jahez', DeliveryConfigPlatform::Jahez->label());
        $this->assertEquals('Marsool', DeliveryConfigPlatform::Marsool->label());
    }

    public function test_delivery_config_platform_has_all_cases(): void
    {
        $cases = DeliveryConfigPlatform::cases();
        $this->assertGreaterThanOrEqual(3, count($cases));

        $values = array_column($cases, 'value');
        $this->assertContains('hungerstation', $values);
        $this->assertContains('jahez', $values);
        $this->assertContains('marsool', $values);
    }

    // ─── MenuSyncTrigger Enum ─────────────────────────────────────

    public function test_menu_sync_trigger_values(): void
    {
        $this->assertEquals('manual', MenuSyncTrigger::Manual->value);
        $this->assertEquals('scheduled', MenuSyncTrigger::Scheduled->value);
        $this->assertEquals('product_change', MenuSyncTrigger::ProductChange->value);
    }

    // ─── WebhookEventType Enum ────────────────────────────────────

    public function test_webhook_event_type_values(): void
    {
        $this->assertEquals('new_order', WebhookEventType::NewOrder->value);
        $this->assertEquals('order_update', WebhookEventType::OrderUpdate->value);
        $this->assertEquals('order_cancelled', WebhookEventType::OrderCancelled->value);
    }

    // ─── Adapter Normalization ────────────────────────────────────

    public function test_hungerstation_adapter_normalizes_order(): void
    {
        $adapter = new HungerStationAdapter([]);
        $result = $adapter->normalizeOrderPayload([
            'order_id' => 'HS-001',
            'customer' => ['name' => 'Ahmad', 'phone' => '+966500000000'],
            'delivery_address' => ['full_address' => 'Riyadh'],
            'subtotal' => 50.00,
            'delivery_fee' => 10.00,
            'total_amount' => 60.00,
            'items' => [
                ['name' => 'Burger', 'quantity' => 2, 'price' => 25.00, 'total' => 50.00],
            ],
            'special_instructions' => 'No onions',
        ]);

        $this->assertEquals('HS-001', $result['external_order_id']);
        $this->assertEquals('Ahmad', $result['customer_name']);
        $this->assertEquals(50.00, $result['subtotal']);
        $this->assertEquals(60.00, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Burger', $result['items'][0]['name']);
        $this->assertEquals('No onions', $result['notes']);
    }

    public function test_jahez_adapter_normalizes_order(): void
    {
        $adapter = new JahezAdapter([]);
        $result = $adapter->normalizeOrderPayload([
            'order_id' => 'J-001',
            'customer_name' => 'Sara',
            'customer_phone' => '+966511111111',
            'sub_total' => 30.00,
            'delivery_fee' => 5.00,
            'total' => 35.00,
            'items' => [
                ['name_en' => 'Salad', 'qty' => 1, 'unit_price' => 30.00],
            ],
        ]);

        $this->assertEquals('J-001', $result['external_order_id']);
        $this->assertEquals('Sara', $result['customer_name']);
        $this->assertEquals(30.00, $result['subtotal']);
    }

    public function test_marsool_adapter_normalizes_order(): void
    {
        $adapter = new MarsoolAdapter([]);
        $result = $adapter->normalizeOrderPayload([
            'reference' => 'M-001',
            'recipient' => ['name' => 'Ali', 'phone' => '+966522222222'],
            'drop_off' => ['address' => 'Jeddah'],
            'pricing' => ['subtotal' => 40, 'delivery_fee' => 8, 'total' => 48],
            'items' => [
                ['title' => 'Shawarma', 'count' => 3, 'price_sar' => 13.33],
            ],
        ]);

        $this->assertEquals('M-001', $result['external_order_id']);
        $this->assertEquals('Ali', $result['customer_name']);
        $this->assertEquals('Jeddah', $result['delivery_address']);
    }

    public function test_generic_adapter_normalizes_order(): void
    {
        $adapter = new GenericDeliveryAdapter([], 'custom');
        $result = $adapter->normalizeOrderPayload([
            'order_id' => 'GEN-001',
            'customer_name' => 'Test',
            'subtotal' => 20,
            'total' => 25,
            'items' => [],
        ]);

        $this->assertEquals('GEN-001', $result['external_order_id']);
        $this->assertEquals('Test', $result['customer_name']);
        $this->assertEquals('custom', $adapter->getPlatformSlug());
    }

    // ─── DTOs ─────────────────────────────────────────────────────

    public function test_ingest_order_dto_from_webhook_payload(): void
    {
        $dto = IngestOrderDTO::fromWebhookPayload(
            storeId: 'store-123',
            platform: 'jahez',
            payload: [
                'external_order_id' => 'J-100',
                'customer_name' => 'Test Customer',
                'customer_phone' => '+966500000000',
                'delivery_address' => 'Riyadh',
                'subtotal' => 50.00,
                'delivery_fee' => 10.00,
                'total' => 60.00,
                'items' => [['name' => 'Item 1']],
            ],
        );

        $this->assertEquals('store-123', $dto->storeId);
        $this->assertEquals('jahez', $dto->platform);
        $this->assertEquals('J-100', $dto->externalOrderId);
        $this->assertEquals('Test Customer', $dto->customerName);
        $this->assertEquals(60.00, $dto->totalAmount);
    }

    public function test_push_order_status_dto(): void
    {
        $dto = new PushOrderStatusDTO(
            deliveryOrderMappingId: 'order-123',
            platform: 'jahez',
            externalOrderId: 'J-100',
            newStatus: 'accepted',
            reason: null,
            estimatedMinutes: 15,
        );

        $this->assertEquals('order-123', $dto->deliveryOrderMappingId);
        $this->assertEquals('accepted', $dto->newStatus);
        $this->assertEquals(15, $dto->estimatedMinutes);
    }

    // ─── Adapter Platform Slugs ───────────────────────────────────

    public function test_adapter_platform_slugs(): void
    {
        $this->assertEquals('hungerstation', (new HungerStationAdapter([]))->getPlatformSlug());
        $this->assertEquals('jahez', (new JahezAdapter([]))->getPlatformSlug());
        $this->assertEquals('marsool', (new MarsoolAdapter([]))->getPlatformSlug());
        $this->assertEquals('custom', (new GenericDeliveryAdapter([], 'custom'))->getPlatformSlug());
    }
}
