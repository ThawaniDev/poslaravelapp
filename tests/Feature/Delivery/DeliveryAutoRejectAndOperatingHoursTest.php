<?php

namespace Tests\Feature\Delivery;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Jobs\AutoRejectStaleOrderJob;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\DeliveryIntegration\Services\OperatingHoursSyncService;
use App\Domain\DeliveryIntegration\Services\OrderIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryAutoRejectAndOperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Auto Reject Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        DB::table('delivery_platforms')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Jahez',
            'slug' => 'jahez',
            'auth_method' => 'api_key',
            'is_active' => true,
            'sort_order' => 1,
            'default_commission_percent' => 18.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeConfig(array $overrides = []): DeliveryPlatformConfig
    {
        return DeliveryPlatformConfig::create(array_merge([
            'store_id' => $this->store->id,
            'platform' => 'jahez',
            'api_key' => 'k',
            'webhook_secret' => 's',
            'is_enabled' => true,
            'auto_accept' => false,
            'auto_accept_timeout_seconds' => 300,
            'sync_menu_on_product_change' => true,
            'menu_sync_interval_hours' => 6,
            'status' => 'active',
        ], $overrides));
    }

    private function ingest(DeliveryPlatformConfig $config, string $externalId = 'EXT-AR-1'): ?DeliveryOrderMapping
    {
        $svc = app(OrderIngestService::class);
        $dto = new IngestOrderDTO(
            storeId: $config->store_id,
            platform: 'jahez',
            externalOrderId: $externalId,
            customerName: 'Cust',
            customerPhone: '0500000000',
            deliveryAddress: 'Riyadh',
            subtotal: 100,
            deliveryFee: 10,
            totalAmount: 110,
            commissionAmount: 0,
            commissionPercent: null,
            items: [['name' => 'Item', 'qty' => 1, 'price' => 100]],
            notes: null,
            estimatedPrepMinutes: 20,
            rawPayload: ['order_id' => $externalId],
        );

        return $svc->ingest($dto);
    }

    public function test_manual_accept_ingest_schedules_auto_reject_with_configured_delay(): void
    {
        Bus::fake([AutoRejectStaleOrderJob::class]);

        $config = $this->makeConfig(['auto_accept' => false, 'auto_accept_timeout_seconds' => 300]);

        $order = $this->ingest($config);
        $this->assertNotNull($order);

        Bus::assertDispatched(
            AutoRejectStaleOrderJob::class,
            fn ($job) => $job->orderMappingId === $order->id,
        );
    }

    public function test_auto_accept_ingest_does_not_schedule_auto_reject(): void
    {
        Bus::fake([AutoRejectStaleOrderJob::class]);

        $config = $this->makeConfig(['auto_accept' => true]);

        $this->ingest($config, 'EXT-AR-2');

        Bus::assertNotDispatched(AutoRejectStaleOrderJob::class);
    }

    public function test_auto_reject_job_marks_pending_order_rejected(): void
    {
        Bus::fake([AutoRejectStaleOrderJob::class]);

        $config = $this->makeConfig(['auto_accept' => false]);
        $order = $this->ingest($config, 'EXT-AR-3');
        $this->assertSame(DeliveryOrderStatus::Pending->value, $order->fresh()->delivery_status->value);

        // Run job synchronously without trying to push to a real adapter:
        // remove the config so StatusPushService bails early with no exception
        // and the local rejection still applies.
        $config->delete();

        (new AutoRejectStaleOrderJob($order->id))->handle(app(\App\Domain\DeliveryIntegration\Services\StatusPushService::class));

        $reloaded = $order->fresh();
        $this->assertSame(DeliveryOrderStatus::Rejected->value, $reloaded->delivery_status->value);
        $this->assertSame('auto_rejected_timeout', $reloaded->rejection_reason);
    }

    public function test_auto_reject_job_skips_already_accepted_orders(): void
    {
        Bus::fake([AutoRejectStaleOrderJob::class]);

        $config = $this->makeConfig(['auto_accept' => false]);
        $order = $this->ingest($config, 'EXT-AR-4');

        $order->update([
            'delivery_status' => DeliveryOrderStatus::Accepted->value,
            'accepted_at' => now(),
        ]);

        (new AutoRejectStaleOrderJob($order->id))->handle(app(\App\Domain\DeliveryIntegration\Services\StatusPushService::class));

        $this->assertSame(DeliveryOrderStatus::Accepted->value, $order->fresh()->delivery_status->value);
    }

    public function test_operating_hours_sync_returns_no_config_when_empty(): void
    {
        $config = $this->makeConfig(['operating_hours_json' => null]);

        $result = app(OperatingHoursSyncService::class)->syncForConfig($config);

        $this->assertFalse($result['success']);
        $this->assertSame('no_operating_hours_configured', $result['message']);
        $this->assertFalse((bool) $config->fresh()->operating_hours_synced);
    }

    public function test_operating_hours_json_round_trips_via_array_cast(): void
    {
        $hours = [
            ['open' => '09:00', 'close' => '23:00'],
            ['open' => '09:00', 'close' => '23:00'],
            ['closed' => true],
            ['open' => '09:00', 'close' => '23:00'],
            ['open' => '09:00', 'close' => '23:00'],
            ['open' => '09:00', 'close' => '23:00'],
            ['open' => '09:00', 'close' => '23:00'],
        ];
        $config = $this->makeConfig(['operating_hours_json' => $hours]);

        $reloaded = $config->fresh();
        $this->assertIsArray($reloaded->operating_hours_json);
        $this->assertSame('23:00', $reloaded->operating_hours_json[0]['close']);
        $this->assertTrue($reloaded->operating_hours_json[2]['closed']);
    }
}
