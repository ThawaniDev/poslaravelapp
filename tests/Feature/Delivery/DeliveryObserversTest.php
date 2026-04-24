<?php

namespace Tests\Feature\Delivery;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\DeliveryIntegration\Jobs\MenuSyncJob;
use App\Domain\DeliveryIntegration\Jobs\ToggleProductAvailabilityJob;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use App\Domain\Inventory\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryObserversTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Obs Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Obs Branch',
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
            'auto_accept' => true,
            'sync_menu_on_product_change' => true,
            'menu_sync_interval_hours' => 6,
            'status' => 'active',
        ], $overrides));
    }

    private function makeProduct(): Product
    {
        $cat = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Mains',
            'sort_order' => 1,
        ]);

        return Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $cat->id,
            'name' => 'Burger',
            'sku' => 'B-1',
            'sell_price' => 25,
            'cost_price' => 10,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $config = $this->makeConfig(['api_key' => 'plaintext-key', 'webhook_secret' => 'plaintext-sec']);

        $rawApiKey = DB::table('delivery_platform_configs')->where('id', $config->id)->value('api_key');
        $rawSecret = DB::table('delivery_platform_configs')->where('id', $config->id)->value('webhook_secret');

        $this->assertNotEquals('plaintext-key', $rawApiKey);
        $this->assertNotEquals('plaintext-sec', $rawSecret);
        $this->assertNotEmpty($rawApiKey);

        $reloaded = DeliveryPlatformConfig::find($config->id);
        $this->assertEquals('plaintext-key', $reloaded->api_key);
        $this->assertEquals('plaintext-sec', $reloaded->webhook_secret);
    }

    public function test_get_credentials_returns_decrypted_payload(): void
    {
        $config = $this->makeConfig([
            'api_key' => 'AK-X',
            'merchant_id' => 'M-9',
            'branch_id_on_platform' => 'B-9',
            'webhook_secret' => 'WS-X',
        ]);

        $creds = $config->getCredentials();

        $this->assertSame('jahez', $creds['platform']);
        $this->assertSame($this->store->id, $creds['store_id']);
        $this->assertSame('AK-X', $creds['api_key']);
        $this->assertSame('M-9', $creds['merchant_id']);
        $this->assertSame('B-9', $creds['branch_id']);
        $this->assertSame('WS-X', $creds['webhook_secret']);
    }

    public function test_stock_observer_dispatches_toggle_when_going_out_of_stock(): void
    {
        Bus::fake([ToggleProductAvailabilityJob::class]);

        $product = $this->makeProduct();
        $config = $this->makeConfig();

        $level = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        // initial create should not trigger
        Bus::assertNotDispatched(ToggleProductAvailabilityJob::class);

        $level->update(['quantity' => 0]);

        Bus::assertDispatched(
            ToggleProductAvailabilityJob::class,
            fn ($job) => $job->configId === $config->id
                && $job->productId === $product->id
                && $job->available === false,
        );
    }

    public function test_stock_observer_dispatches_toggle_when_back_in_stock(): void
    {
        Bus::fake([ToggleProductAvailabilityJob::class]);

        $product = $this->makeProduct();
        $config = $this->makeConfig();

        $level = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $level->update(['quantity' => 4]);

        Bus::assertDispatched(
            ToggleProductAvailabilityJob::class,
            fn ($job) => $job->available === true && $job->productId === $product->id,
        );
    }

    public function test_stock_observer_skips_when_quantity_unchanged_relative_to_zero(): void
    {
        Bus::fake([ToggleProductAvailabilityJob::class]);

        $product = $this->makeProduct();
        $this->makeConfig();

        $level = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 8,
            'reserved_quantity' => 0,
        ]);

        $level->update(['quantity' => 12]);

        Bus::assertNotDispatched(ToggleProductAvailabilityJob::class);
    }

    public function test_stock_observer_ignores_disabled_configs(): void
    {
        Bus::fake([ToggleProductAvailabilityJob::class]);

        $product = $this->makeProduct();
        $this->makeConfig(['is_enabled' => false]);

        $level = StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
        ]);
        $level->update(['quantity' => 0]);

        Bus::assertNotDispatched(ToggleProductAvailabilityJob::class);
    }

    public function test_product_observer_dispatches_menu_sync_on_create(): void
    {
        Bus::fake([MenuSyncJob::class]);
        Cache::flush();

        $config = $this->makeConfig();

        $this->makeProduct();

        Bus::assertDispatched(
            MenuSyncJob::class,
            fn ($job) => true,
        );
    }

    public function test_product_observer_debounces_repeated_changes(): void
    {
        Bus::fake([MenuSyncJob::class]);
        Cache::flush();

        $this->makeConfig();

        $product = $this->makeProduct(); // dispatch #1

        // Multiple updates within debounce window should not re-dispatch
        $product->update(['name' => 'Burger v2']);
        $product->update(['name' => 'Burger v3']);
        $product->update(['name' => 'Burger v4']);

        Bus::assertDispatchedTimes(MenuSyncJob::class, 1);
    }

    public function test_product_observer_skips_when_sync_menu_on_product_change_disabled(): void
    {
        Bus::fake([MenuSyncJob::class]);
        Cache::flush();

        $this->makeConfig(['sync_menu_on_product_change' => false]);

        $this->makeProduct();

        Bus::assertNotDispatched(MenuSyncJob::class);
    }

    public function test_product_observer_skips_when_no_enabled_configs(): void
    {
        Bus::fake([MenuSyncJob::class]);
        Cache::flush();

        $this->makeConfig(['is_enabled' => false]);

        $this->makeProduct();

        Bus::assertNotDispatched(MenuSyncJob::class);
    }
}
