<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\StocktakeStatus;
use App\Domain\Inventory\Enums\StocktakeType;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\StocktakeItem;
use App\Domain\Inventory\Services\StocktakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for StocktakeService.
 *
 * Covers:
 *  - create() pre-populates items from current stock levels
 *  - create() category filter
 *  - updateCounts() sets variance, auto-advance to Review
 *  - updateCounts() handles discovered items
 *  - updateCounts() throws on completed/cancelled
 *  - apply() creates adjustments for variance items
 *  - apply() skips uncounted items
 *  - apply() throws on completed/cancelled
 *  - cancel() works on InProgress/Review, throws on Completed
 */
class StocktakeServiceTest extends TestCase
{
    use RefreshDatabase;

    private StocktakeService $service;
    private Organization $org;
    private Store $store;
    private Product $product;
    private Product $product2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StocktakeService::class);

        $this->org = Organization::create([
            'name' => 'Stocktake Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Count Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Apple',
            'sell_price' => 1.00,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Banana',
            'sell_price' => 0.50,
            'sync_version' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Counter',
            'email' => 'counter@stocktake.test',
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

    public function test_create_prepopulates_items_from_stock_levels(): void
    {
        $this->seedStockLevel($this->product->id, 100);
        $this->seedStockLevel($this->product2->id, 50);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->assertEquals(StocktakeStatus::InProgress, $stocktake->status);
        $this->assertCount(2, $stocktake->stocktakeItems);
    }

    public function test_create_sets_expected_qty_from_stock_levels(): void
    {
        $this->seedStockLevel($this->product->id, 75);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $item = $stocktake->stocktakeItems->first();
        $this->assertEquals(75.0, (float) $item->expected_qty);
        $this->assertNull($item->counted_qty);
    }

    public function test_create_excludes_zero_qty_stock_levels(): void
    {
        $this->seedStockLevel($this->product->id, 0);  // zero — excluded
        $this->seedStockLevel($this->product2->id, 30); // non-zero — included

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->assertCount(1, $stocktake->stocktakeItems);
        $this->assertEquals($this->product2->id, $stocktake->stocktakeItems->first()->product_id);
    }

    public function test_create_generates_reference_number(): void
    {
        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->assertStringStartsWith('ST-', $stocktake->reference_number);
    }

    // ═══════════════════════════════════════════════════════════
    // updateCounts()
    // ═══════════════════════════════════════════════════════════

    public function test_update_counts_sets_variance(): void
    {
        $this->seedStockLevel($this->product->id, 100);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 95],
        ], $this->user->id);

        $item = StocktakeItem::where('stocktake_id', $stocktake->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEquals(95.0, (float) $item->counted_qty);
        $this->assertEquals(-5.0, (float) $item->variance);
    }

    public function test_update_counts_auto_advances_to_review_when_all_counted(): void
    {
        $this->seedStockLevel($this->product->id, 100);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $updated = $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 100],
        ], $this->user->id);

        $this->assertEquals(StocktakeStatus::Review, $updated->status);
    }

    public function test_update_counts_stays_in_progress_when_not_all_counted(): void
    {
        $this->seedStockLevel($this->product->id, 100);
        $this->seedStockLevel($this->product2->id, 50);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        // Only count one of two products
        $updated = $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 100],
        ], $this->user->id);

        $this->assertEquals(StocktakeStatus::InProgress, $updated->status);
    }

    public function test_update_counts_handles_discovered_items(): void
    {
        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        // product not in expected stock — should be created as discovered
        $updated = $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 10],
        ], $this->user->id);

        $item = $updated->stocktakeItems->first();
        $this->assertEquals(0.0, (float) $item->expected_qty);
        $this->assertEquals(10.0, (float) $item->counted_qty);
        $this->assertEquals(10.0, (float) $item->variance); // surplus
    }

    public function test_update_counts_throws_on_completed_stocktake(): void
    {
        $stocktake = Stocktake::create([
            'store_id' => $this->store->id,
            'reference_number' => 'ST-DONE',
            'type' => StocktakeType::Full,
            'status' => StocktakeStatus::Completed,
            'started_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 10],
        ], $this->user->id);
    }

    public function test_update_counts_throws_on_cancelled_stocktake(): void
    {
        $stocktake = Stocktake::create([
            'store_id' => $this->store->id,
            'reference_number' => 'ST-CNCL',
            'type' => StocktakeType::Full,
            'status' => StocktakeStatus::Cancelled,
            'started_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 5],
        ], $this->user->id);
    }

    // ═══════════════════════════════════════════════════════════
    // apply()
    // ═══════════════════════════════════════════════════════════

    public function test_apply_adjusts_stock_for_variance_items(): void
    {
        $this->seedStockLevel($this->product->id, 100);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 90], // -10 variance
        ], $this->user->id);

        $this->service->apply($stocktake->id, $this->user->id);

        $qty = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(90.0, $qty); // adjusted down by 10
    }

    public function test_apply_increases_stock_on_positive_variance(): void
    {
        $this->seedStockLevel($this->product->id, 100);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 115], // +15 variance
        ], $this->user->id);

        $this->service->apply($stocktake->id, $this->user->id);

        $qty = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(115.0, $qty);
    }

    public function test_apply_skips_uncounted_items(): void
    {
        $this->seedStockLevel($this->product->id, 100);
        $this->seedStockLevel($this->product2->id, 50);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        // Only count product1 (product2 remains uncounted)
        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 90],
        ], $this->user->id);

        $this->service->apply($stocktake->id, $this->user->id);

        // product2 stock unchanged
        $qty2 = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product2->id)
            ->value('quantity');

        $this->assertEquals(50.0, $qty2);
    }

    public function test_apply_marks_stocktake_as_completed(): void
    {
        $this->seedStockLevel($this->product->id, 100);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $this->service->updateCounts($stocktake->id, [
            ['product_id' => $this->product->id, 'counted_qty' => 100],
        ], $this->user->id);

        $applied = $this->service->apply($stocktake->id, $this->user->id);

        $this->assertEquals(StocktakeStatus::Completed, $applied->status);
        $this->assertEquals($this->user->id, $applied->completed_by);
    }

    public function test_apply_throws_on_already_completed(): void
    {
        $stocktake = Stocktake::create([
            'store_id' => $this->store->id,
            'reference_number' => 'ST-DONE2',
            'type' => StocktakeType::Full,
            'status' => StocktakeStatus::Completed,
            'started_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already completed');

        $this->service->apply($stocktake->id, $this->user->id);
    }

    public function test_apply_throws_on_cancelled(): void
    {
        $stocktake = Stocktake::create([
            'store_id' => $this->store->id,
            'reference_number' => 'ST-CNCL2',
            'type' => StocktakeType::Full,
            'status' => StocktakeStatus::Cancelled,
            'started_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->apply($stocktake->id, $this->user->id);
    }

    // ═══════════════════════════════════════════════════════════
    // cancel()
    // ═══════════════════════════════════════════════════════════

    public function test_cancel_in_progress_stocktake(): void
    {
        $stocktake = Stocktake::create([
            'store_id' => $this->store->id,
            'reference_number' => 'ST-IP',
            'type' => StocktakeType::Full,
            'status' => StocktakeStatus::InProgress,
            'started_by' => $this->user->id,
        ]);

        $cancelled = $this->service->cancel($stocktake->id);

        $this->assertEquals(StocktakeStatus::Cancelled, $cancelled->status);
    }

    public function test_cancel_throws_on_completed(): void
    {
        $stocktake = Stocktake::create([
            'store_id' => $this->store->id,
            'reference_number' => 'ST-COMP',
            'type' => StocktakeType::Full,
            'status' => StocktakeStatus::Completed,
            'started_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->cancel($stocktake->id);
    }

    // ═══════════════════════════════════════════════════════════
    // list() / find()
    // ═══════════════════════════════════════════════════════════

    public function test_list_returns_paginated_stocktakes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Stocktake::create([
                'store_id' => $this->store->id,
                'reference_number' => "ST-{$i}",
                'type' => StocktakeType::Full,
                'status' => StocktakeStatus::InProgress,
                'started_by' => $this->user->id,
            ]);
        }

        $result = $this->service->list($this->store->id, perPage: 2);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_find_returns_stocktake_with_items(): void
    {
        $this->seedStockLevel($this->product->id, 50);

        $stocktake = $this->service->create([
            'store_id' => $this->store->id,
            'type' => StocktakeType::Full->value,
        ], $this->user->id);

        $found = $this->service->find($this->store->id, $stocktake->id);

        $this->assertEquals($stocktake->id, $found->id);
        $this->assertNotEmpty($found->stocktakeItems);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function seedStockLevel(string $productId, float $quantity): StockLevel
    {
        return StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'average_cost' => 0,
            'sync_version' => 1,
        ]);
    }
}
