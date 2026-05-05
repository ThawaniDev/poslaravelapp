<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for StockService — the core stock accounting engine.
 *
 * Covers:
 *  - adjustStock() signed quantity routing (in vs out)
 *  - Weighted Average Cost (WAC) recalculation on receipts
 *  - Idempotency key deduplication
 *  - Batch FEFO consumption
 *  - reserve() / releaseReservation()
 *  - assertSufficientStock() with allow_negative_stock toggle
 *  - getOrCreate() upsert
 *  - levels() pagination & filters
 *  - movements() pagination & product filter
 *  - expiryAlerts() threshold
 *  - lowStockItems() reorder logic
 *  - setReorderPoint()
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;
    private Organization $org;
    private Store $store;
    private Product $product;
    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockService::class);

        $this->org = Organization::create([
            'name' => 'Unit Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Unit Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee',
            'sell_price' => 10.00,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Tea',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // adjustStock — signed quantity routing
    // ═══════════════════════════════════════════════════════════

    public function test_receipt_adds_positive_quantity(): void
    {
        $movement = $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 50,
        );

        $this->assertEquals(50, (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->value('quantity'));
        $this->assertEquals(50, (float) $movement->quantity);
    }

    public function test_sale_subtracts_quantity(): void
    {
        $this->seedStockLevel(100);

        $movement = $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 30,
        );

        $this->assertEquals(70, $this->getQuantity());
        $this->assertEquals(-30, (float) $movement->quantity);
    }

    public function test_adjustment_in_adds_quantity(): void
    {
        $this->seedStockLevel(20);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::AdjustmentIn,
            quantity: 15,
        );

        $this->assertEquals(35, $this->getQuantity());
    }

    public function test_adjustment_out_subtracts_quantity(): void
    {
        $this->seedStockLevel(40);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::AdjustmentOut,
            quantity: 10,
        );

        $this->assertEquals(30, $this->getQuantity());
    }

    public function test_transfer_out_subtracts_quantity(): void
    {
        $this->seedStockLevel(60);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::TransferOut,
            quantity: 20,
        );

        $this->assertEquals(40, $this->getQuantity());
    }

    public function test_transfer_in_adds_quantity(): void
    {
        $this->seedStockLevel(0);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::TransferIn,
            quantity: 25,
        );

        $this->assertEquals(25, $this->getQuantity());
    }

    public function test_waste_subtracts_quantity(): void
    {
        $this->seedStockLevel(50);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Waste,
            quantity: 5,
        );

        $this->assertEquals(45, $this->getQuantity());
    }

    public function test_recipe_deduction_subtracts_quantity(): void
    {
        $this->seedStockLevel(80);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::RecipeDeduction,
            quantity: 8,
        );

        $this->assertEquals(72, $this->getQuantity());
    }

    // ═══════════════════════════════════════════════════════════
    // Weighted Average Cost (WAC)
    // ═══════════════════════════════════════════════════════════

    public function test_wac_recalculated_on_first_receipt(): void
    {
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 100,
            unitCost: 5.00,
        );

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEqualsWithDelta(5.00, (float) $level->average_cost, 0.001);
    }

    public function test_wac_recalculated_on_second_receipt(): void
    {
        // First receipt: 100 units @ 5.00 => avg = 5.00
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 100,
            unitCost: 5.00,
        );

        // Second receipt: 50 units @ 8.00
        // New avg = (100*5 + 50*8) / 150 = 900/150 = 6.00
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 50,
            unitCost: 8.00,
        );

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEqualsWithDelta(6.00, (float) $level->average_cost, 0.001);
        $this->assertEquals(150, (float) $level->quantity);
    }

    public function test_wac_not_changed_on_sale(): void
    {
        $this->seedStockLevel(100, averageCost: 7.50);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 10,
        );

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEqualsWithDelta(7.50, (float) $level->average_cost, 0.001);
    }

    public function test_wac_not_changed_when_unit_cost_zero(): void
    {
        $this->seedStockLevel(100, averageCost: 5.00);

        // Receipt with unit cost 0 should not change WAC
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 50,
            unitCost: 0,
        );

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEqualsWithDelta(5.00, (float) $level->average_cost, 0.001);
    }

    // ═══════════════════════════════════════════════════════════
    // Idempotency
    // ═══════════════════════════════════════════════════════════

    public function test_idempotency_key_prevents_double_apply(): void
    {
        $this->seedStockLevel(0);

        $key = 'receipt-abc-123';
        $refId = (string) \Illuminate\Support\Str::uuid();

        // First call
        $m1 = $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 50,
            referenceType: StockReferenceType::GoodsReceipt,
            referenceId: $refId,
            idempotencyKey: $key,
        );

        // Duplicate call with same key
        $m2 = $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 50,
            referenceType: StockReferenceType::GoodsReceipt,
            referenceId: $refId,
            idempotencyKey: $key,
        );

        // Should return same movement record, not create a new one
        $this->assertEquals($m1->id, $m2->id);
        // Stock should only be 50, not 100
        $this->assertEquals(50, $this->getQuantity());
        // Only one movement row
        $this->assertEquals(1, StockMovement::where('idempotency_key', $key)->count());
    }

    public function test_different_idempotency_keys_both_apply(): void
    {
        $this->seedStockLevel(0);

        $refId = (string) \Illuminate\Support\Str::uuid();

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 30,
            referenceType: StockReferenceType::GoodsReceipt,
            referenceId: $refId,
            idempotencyKey: 'key-1',
        );

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 20,
            referenceType: StockReferenceType::GoodsReceipt,
            referenceId: $refId,
            idempotencyKey: 'key-2',
        );

        $this->assertEquals(50, $this->getQuantity());
    }

    // ═══════════════════════════════════════════════════════════
    // Batch FEFO consumption
    // ═══════════════════════════════════════════════════════════

    public function test_fefo_consumes_earliest_expiry_first(): void
    {
        // Enable batch tracking
        StoreSettings::create([
            'store_id' => $this->store->id,
            'enable_batch_tracking' => true,
            'track_inventory' => true,
        ]);

        $this->seedStockLevel(100);

        // Batch 1: expires soonest
        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-EARLY',
            'expiry_date' => now()->addDays(5),
            'quantity' => 30,
        ]);

        // Batch 2: expires later
        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-LATE',
            'expiry_date' => now()->addDays(30),
            'quantity' => 70,
        ]);

        // Deduct 25 units — should come from BATCH-EARLY
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 25,
        );

        $earlyBatch = StockBatch::where('batch_number', 'BATCH-EARLY')->first();
        $lateBatch = StockBatch::where('batch_number', 'BATCH-LATE')->first();

        $this->assertEquals(5.0, (float) $earlyBatch->quantity); // 30 - 25
        $this->assertEquals(70.0, (float) $lateBatch->quantity); // unchanged
    }

    public function test_fefo_crosses_batches_when_first_exhausted(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'enable_batch_tracking' => true,
            'track_inventory' => true,
        ]);

        $this->seedStockLevel(100);

        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-A',
            'expiry_date' => now()->addDays(3),
            'quantity' => 10,
        ]);

        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-B',
            'expiry_date' => now()->addDays(15),
            'quantity' => 50,
        ]);

        // Deduct 35 — exhaust A (10) and take 25 from B
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 35,
        );

        $this->assertEquals(0, (float) StockBatch::where('batch_number', 'BATCH-A')->value('quantity'));
        $this->assertEquals(25, (float) StockBatch::where('batch_number', 'BATCH-B')->value('quantity'));
    }

    public function test_fefo_skipped_when_batch_tracking_disabled(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'enable_batch_tracking' => false,
            'track_inventory' => true,
        ]);

        $this->seedStockLevel(100);

        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-X',
            'expiry_date' => now()->addDays(5),
            'quantity' => 40,
        ]);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 20,
        );

        // Batch untouched since tracking disabled
        $this->assertEquals(40.0, (float) StockBatch::where('batch_number', 'BATCH-X')->value('quantity'));
    }

    // ═══════════════════════════════════════════════════════════
    // reserve() / releaseReservation()
    // ═══════════════════════════════════════════════════════════

    public function test_reserve_increments_reserved_quantity(): void
    {
        $this->seedStockLevel(100);

        $this->service->reserve($this->store->id, $this->product->id, 30);

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEquals(30.0, (float) $level->reserved_quantity);
        $this->assertEquals(100.0, (float) $level->quantity); // on-hand unchanged
    }

    public function test_reserve_fails_when_insufficient_available(): void
    {
        $this->seedStockLevel(20, reserved: 15); // only 5 available

        $this->expectException(\RuntimeException::class);

        $this->service->reserve($this->store->id, $this->product->id, 10); // needs 10
    }

    public function test_release_reservation_decrements_reserved(): void
    {
        $this->seedStockLevel(100, reserved: 40);

        $this->service->releaseReservation($this->store->id, $this->product->id, 20);

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEquals(20.0, (float) $level->reserved_quantity);
    }

    public function test_release_reservation_floors_at_zero(): void
    {
        $this->seedStockLevel(50, reserved: 5);

        // Release more than reserved — should floor at 0
        $this->service->releaseReservation($this->store->id, $this->product->id, 100);

        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEquals(0.0, (float) $level->reserved_quantity);
    }

    // ═══════════════════════════════════════════════════════════
    // assertSufficientStock
    // ═══════════════════════════════════════════════════════════

    public function test_assert_sufficient_stock_throws_when_below(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'track_inventory' => true,
            'allow_negative_stock' => false,
        ]);

        $this->seedStockLevel(5);

        $this->expectException(\RuntimeException::class);

        $this->service->assertSufficientStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            needed: 10,
        );
    }

    public function test_assert_sufficient_stock_passes_with_allow_negative(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'track_inventory' => true,
            'allow_negative_stock' => true,
        ]);

        $this->seedStockLevel(5);

        // Should not throw
        $this->service->assertSufficientStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            needed: 100,
        );

        $this->assertTrue(true); // Reached here
    }

    public function test_assert_sufficient_stock_skipped_when_tracking_disabled(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'track_inventory' => false,
            'allow_negative_stock' => false,
        ]);

        $this->seedStockLevel(0);

        // Should not throw even with zero stock when tracking disabled
        $this->service->assertSufficientStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            needed: 1000,
        );

        $this->assertTrue(true);
    }

    // ═══════════════════════════════════════════════════════════
    // getOrCreate
    // ═══════════════════════════════════════════════════════════

    public function test_get_or_create_creates_new_level(): void
    {
        $level = $this->service->getOrCreate($this->store->id, $this->product->id);

        $this->assertEquals($this->store->id, $level->store_id);
        $this->assertEquals($this->product->id, $level->product_id);
        $this->assertEquals(0.0, (float) $level->quantity);
    }

    public function test_get_or_create_returns_existing_level(): void
    {
        $this->seedStockLevel(75);

        $level = $this->service->getOrCreate($this->store->id, $this->product->id);

        $this->assertEquals(75.0, (float) $level->quantity);
        $this->assertEquals(1, StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)->count());
    }

    // ═══════════════════════════════════════════════════════════
    // levels() filters
    // ═══════════════════════════════════════════════════════════

    public function test_levels_paginates_results(): void
    {
        $this->seedStockLevel(10);

        $result = $this->service->levels($this->store->id, perPage: 5);

        $this->assertCount(1, $result->items());
    }

    public function test_levels_filters_low_stock(): void
    {
        $this->seedStockLevel(3, reorderPoint: 10); // low

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product2->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'reorder_point' => 10,
            'sync_version' => 1,
        ]);

        $result = $this->service->levels($this->store->id, filters: ['low_stock' => true]);

        $this->assertCount(1, $result->items());
        $this->assertEquals($this->product->id, $result->items()[0]->product_id);
    }

    // ═══════════════════════════════════════════════════════════
    // expiryAlerts
    // ═══════════════════════════════════════════════════════════

    public function test_expiry_alerts_returns_batches_within_threshold(): void
    {
        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'NEAR',
            'expiry_date' => now()->addDays(7),
            'quantity' => 10,
        ]);

        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'FAR',
            'expiry_date' => now()->addDays(60),
            'quantity' => 10,
        ]);

        $result = $this->service->expiryAlerts($this->store->id, daysAhead: 30);

        $this->assertCount(1, $result->items());
        $this->assertEquals('NEAR', $result->items()[0]->batch_number);
    }

    public function test_expiry_alerts_excludes_zero_qty_batches(): void
    {
        StockBatch::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'batch_number' => 'EMPTY',
            'expiry_date' => now()->addDays(5),
            'quantity' => 0,
        ]);

        $result = $this->service->expiryAlerts($this->store->id, daysAhead: 30);

        $this->assertCount(0, $result->items());
    }

    // ═══════════════════════════════════════════════════════════
    // lowStockItems
    // ═══════════════════════════════════════════════════════════

    public function test_low_stock_items_returns_only_below_reorder(): void
    {
        $this->seedStockLevel(3, reorderPoint: 10); // below reorder

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product2->id,
            'quantity' => 50,
            'reorder_point' => 10,
            'sync_version' => 1,
        ]);

        $items = $this->service->lowStockItems($this->store->id);

        $this->assertCount(1, $items);
        $this->assertEquals($this->product->id, $items->first()->product_id);
    }

    public function test_low_stock_items_excludes_without_reorder_point(): void
    {
        // No reorder_point set
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 0,
            'sync_version' => 1,
        ]);

        $items = $this->service->lowStockItems($this->store->id);

        $this->assertCount(0, $items);
    }

    // ═══════════════════════════════════════════════════════════
    // setReorderPoint
    // ═══════════════════════════════════════════════════════════

    public function test_set_reorder_point_updates_level(): void
    {
        $level = $this->seedStockLevel(100);

        $updated = $this->service->setReorderPoint($level->id, 20, 500);

        $this->assertEquals(20.0, (float) $updated->reorder_point);
        $this->assertEquals(500.0, (float) $updated->max_stock_level);
    }

    public function test_set_reorder_point_without_max_level(): void
    {
        $level = $this->seedStockLevel(100, maxLevel: 200);

        $updated = $this->service->setReorderPoint($level->id, 15);

        $this->assertEquals(15.0, (float) $updated->reorder_point);
        $this->assertEquals(200.0, (float) $updated->max_stock_level); // unchanged
    }

    // ═══════════════════════════════════════════════════════════
    // movements()
    // ═══════════════════════════════════════════════════════════

    public function test_movements_filters_by_product(): void
    {
        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Receipt,
            quantity: 10,
        );

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product2->id,
            type: StockMovementType::Receipt,
            quantity: 5,
        );

        $result = $this->service->movements($this->store->id, $this->product->id);

        $this->assertCount(1, $result->items());
        $this->assertEquals($this->product->id, $result->items()[0]->product_id);
    }

    // ═══════════════════════════════════════════════════════════
    // available()
    // ═══════════════════════════════════════════════════════════

    public function test_available_returns_quantity_minus_reserved(): void
    {
        $this->seedStockLevel(100, reserved: 25);

        $available = $this->service->available($this->store->id, $this->product->id);

        $this->assertEquals(75.0, $available);
    }

    public function test_available_returns_zero_when_no_stock_level(): void
    {
        $available = $this->service->available(
            (string) \Illuminate\Support\Str::uuid(),
            (string) \Illuminate\Support\Str::uuid(),
        );
        $this->assertEquals(0.0, $available);
    }

    // ═══════════════════════════════════════════════════════════
    // stock_movements audit trail immutability
    // ═══════════════════════════════════════════════════════════

    public function test_each_adjustment_creates_immutable_movement_record(): void
    {
        $this->seedStockLevel(100);

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 10,
        );

        $this->service->adjustStock(
            storeId: $this->store->id,
            productId: $this->product->id,
            type: StockMovementType::Sale,
            quantity: 5,
        );

        $movementCount = StockMovement::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->count();

        $this->assertEquals(2, $movementCount);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function seedStockLevel(
        float $quantity,
        float $reserved = 0,
        float $averageCost = 0,
        ?float $reorderPoint = null,
        ?float $maxLevel = null,
    ): StockLevel {
        return StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
            'average_cost' => $averageCost,
            'reorder_point' => $reorderPoint,
            'max_stock_level' => $maxLevel,
            'sync_version' => 1,
        ]);
    }

    private function getQuantity(): float
    {
        return (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');
    }
}
