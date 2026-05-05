<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReason;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\WasteRecord;
use App\Domain\Inventory\Services\WasteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for WasteService.
 *
 * Covers:
 *  - create() records waste and deducts stock
 *  - create() auto-fills unit_cost from average_cost when omitted
 *  - create() respects assertSufficientStock
 *  - list() filters (product_id, reason, date_from, date_to)
 */
class WasteServiceTest extends TestCase
{
    use RefreshDatabase;

    private WasteService $service;
    private Organization $org;
    private Store $store;
    private Product $product;
    private Product $product2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WasteService::class);

        $this->org = Organization::create([
            'name' => 'Waste Test Org',
            'business_type' => 'restaurant',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Waste Store',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Milk Carton',
            'sell_price' => 3.00,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Eggs',
            'sell_price' => 2.00,
            'sync_version' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Staff',
            'email' => 'staff@waste.test',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // create()
    // ═══════════════════════════════════════════════════════════

    public function test_create_records_waste_and_deducts_stock(): void
    {
        $this->seedStockLevel(50);

        $waste = $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => 3.00,
            'reason' => WasteReason::Expired->value,
        ], $this->user->id);

        $this->assertInstanceOf(WasteRecord::class, $waste);
        $this->assertEquals(10.0, (float) $waste->quantity);
        $this->assertEquals(WasteReason::Expired, $waste->reason);

        // Stock reduced by 10
        $this->assertEquals(40.0, $this->getQuantity($this->product->id));
    }

    public function test_create_creates_waste_movement(): void
    {
        $this->seedStockLevel(100);

        $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 2.00,
            'reason' => WasteReason::Damaged->value,
        ], $this->user->id);

        $movement = StockMovement::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->where('type', StockMovementType::Waste->value)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-5.0, (float) $movement->quantity);
    }

    public function test_create_auto_fills_unit_cost_from_average_cost(): void
    {
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 80,
            'average_cost' => 4.500, // WAC
            'reserved_quantity' => 0,
            'sync_version' => 1,
        ]);

        // Omit unit_cost — should auto-fill from average_cost
        $waste = $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'reason' => WasteReason::Spillage->value,
        ], $this->user->id);

        $this->assertEqualsWithDelta(4.500, (float) $waste->unit_cost, 0.001);
    }

    public function test_create_throws_when_insufficient_stock(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'track_inventory' => true,
            'allow_negative_stock' => false,
        ]);

        $this->seedStockLevel(5);

        $this->expectException(\RuntimeException::class);

        $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 20, // more than available
            'unit_cost' => 1.00,
            'reason' => WasteReason::Other->value,
        ], $this->user->id);
    }

    public function test_create_with_batch_number(): void
    {
        $this->seedStockLevel(100);

        $waste = $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_cost' => 2.50,
            'reason' => WasteReason::Expired->value,
            'batch_number' => 'BATCH-001',
        ], $this->user->id);

        $this->assertEquals('BATCH-001', $waste->batch_number);
    }

    public function test_create_with_notes(): void
    {
        $this->seedStockLevel(50);

        $waste = $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_cost' => 1.00,
            'reason' => WasteReason::QualityIssue->value,
            'notes' => 'Found during morning inspection',
        ], $this->user->id);

        $this->assertEquals('Found during morning inspection', $waste->notes);
    }

    public function test_create_records_recorded_by(): void
    {
        $this->seedStockLevel(50);

        $waste = $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Overproduction->value,
        ], $this->user->id);

        $this->assertEquals($this->user->id, $waste->recorded_by);
    }

    // ═══════════════════════════════════════════════════════════
    // list() filters
    // ═══════════════════════════════════════════════════════════

    public function test_list_filters_by_product_id(): void
    {
        $this->seedStockLevel(100, $this->product->id);
        $this->seedStockLevel(100, $this->product2->id);

        $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Expired->value,
        ], $this->user->id);

        $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product2->id,
            'quantity' => 3,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Damaged->value,
        ], $this->user->id);

        $result = $this->service->list($this->store->id, ['product_id' => $this->product->id]);

        $this->assertCount(1, $result->items());
        $this->assertEquals($this->product->id, $result->items()[0]->product_id);
    }

    public function test_list_filters_by_reason(): void
    {
        $this->seedStockLevel(100);

        $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Expired->value,
        ], $this->user->id);

        $this->service->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Damaged->value,
        ], $this->user->id);

        $result = $this->service->list($this->store->id, ['reason' => WasteReason::Expired->value]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(WasteReason::Expired->value, $result->items()[0]->reason->value);
    }

    public function test_list_filters_by_date_range(): void
    {
        $this->seedStockLevel(100);

        // Create an old waste record
        $old = WasteRecord::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Expired,
            'recorded_by' => $this->user->id,
        ]);
        \Illuminate\Support\Facades\DB::table('waste_records')
            ->where('id', $old->id)
            ->update(['created_at' => now()->subDays(10)]);

        // Create a recent waste record
        WasteRecord::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_cost' => 1.00,
            'reason' => WasteReason::Damaged,
            'recorded_by' => $this->user->id,
        ]);

        $result = $this->service->list($this->store->id, [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(WasteReason::Damaged, $result->items()[0]->reason);
    }

    public function test_list_returns_paginated_results(): void
    {
        $this->seedStockLevel(1000);

        for ($i = 0; $i < 5; $i++) {
            $this->service->create([
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_cost' => 1.00,
                'reason' => WasteReason::Spillage->value,
            ], $this->user->id);
        }

        $result = $this->service->list($this->store->id, [], perPage: 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(5, $result->total());
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function seedStockLevel(float $quantity, ?string $productId = null): StockLevel
    {
        return StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $productId ?? $this->product->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'average_cost' => 0,
            'sync_version' => 1,
        ]);
    }

    private function getQuantity(string $productId): float
    {
        return (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $productId)
            ->value('quantity');
    }
}
