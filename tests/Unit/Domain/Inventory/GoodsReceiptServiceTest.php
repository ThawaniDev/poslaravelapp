<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\GoodsReceiptStatus;
use App\Domain\Inventory\Models\GoodsReceipt;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Services\GoodsReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for GoodsReceiptService.
 *
 * Covers:
 *  - create() creates Draft goods receipt with items
 *  - create() calculates total_cost correctly
 *  - confirm() adds stock levels
 *  - confirm() recalculates WAC
 *  - confirm() creates StockBatch when batch_number or expiry_date present
 *  - confirm() throws when already confirmed
 *  - list() / find()
 */
class GoodsReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoodsReceiptService $service;
    private Organization $org;
    private Store $store;
    private Product $product;
    private Product $product2;
    private Supplier $supplier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GoodsReceiptService::class);

        $this->org = Organization::create([
            'name' => 'GR Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'GR Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Rice 5kg',
            'sell_price' => 20.00,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Sugar 1kg',
            'sell_price' => 5.00,
            'sync_version' => 1,
        ]);

        $this->supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Food Supplier Co.',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Receiver',
            'email' => 'receiver@gr.test',
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

    public function test_create_returns_draft_goods_receipt(): void
    {
        $receipt = $this->service->create(
            data: [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reference_number' => 'GR-2025-001',
                'received_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity' => 50, 'unit_cost' => 10.00],
            ],
        );

        $this->assertEquals(GoodsReceiptStatus::Draft, $receipt->status);
        $this->assertEquals($this->store->id, $receipt->store_id);
        $this->assertEquals('GR-2025-001', $receipt->reference_number);
        $this->assertCount(1, $receipt->goodsReceiptItems);
    }

    public function test_create_calculates_total_cost(): void
    {
        $receipt = $this->service->create(
            data: [
                'store_id' => $this->store->id,
                'received_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5.00],  // 50
                ['product_id' => $this->product2->id, 'quantity' => 20, 'unit_cost' => 3.00], // 60
            ],
        );

        $this->assertEqualsWithDelta(110.0, (float) $receipt->total_cost, 0.001);
    }

    public function test_create_does_not_affect_stock_levels(): void
    {
        $this->service->create(
            data: ['store_id' => $this->store->id, 'received_by' => $this->user->id],
            items: [
                ['product_id' => $this->product->id, 'quantity' => 50, 'unit_cost' => 10.00],
            ],
        );

        $qty = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertNull($qty); // No stock level created yet
    }

    // ═══════════════════════════════════════════════════════════
    // confirm()
    // ═══════════════════════════════════════════════════════════

    public function test_confirm_increases_stock_level(): void
    {
        $receipt = $this->createDraftReceipt(qty: 100, unitCost: 8.00);

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $qty = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(100.0, $qty);
    }

    public function test_confirm_recalculates_wac(): void
    {
        $receipt = $this->createDraftReceipt(qty: 100, unitCost: 8.00);

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $avgCost = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('average_cost');

        $this->assertEqualsWithDelta(8.00, $avgCost, 0.001);
    }

    public function test_confirm_changes_status_to_confirmed(): void
    {
        $receipt = $this->createDraftReceipt(qty: 50, unitCost: 5.00);

        $confirmed = $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $this->assertEquals(GoodsReceiptStatus::Confirmed, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
    }

    public function test_confirm_creates_stock_batch_when_batch_number_present(): void
    {
        $receipt = $this->service->create(
            data: ['store_id' => $this->store->id, 'received_by' => $this->user->id],
            items: [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 50,
                    'unit_cost' => 10.00,
                    'batch_number' => 'BATCH-2025-001',
                    'expiry_date' => '2026-06-01',
                ],
            ],
        );

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $batch = StockBatch::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->where('batch_number', 'BATCH-2025-001')
            ->first();

        $this->assertNotNull($batch);
        $this->assertEquals(50.0, (float) $batch->quantity);
        $this->assertEquals('2026-06-01', $batch->expiry_date->toDateString());
    }

    public function test_confirm_creates_batch_when_only_expiry_date_present(): void
    {
        $receipt = $this->service->create(
            data: ['store_id' => $this->store->id, 'received_by' => $this->user->id],
            items: [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'unit_cost' => 7.00,
                    'expiry_date' => '2025-12-31',
                ],
            ],
        );

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $batch = StockBatch::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($batch);
        $this->assertEquals(20.0, (float) $batch->quantity);
    }

    public function test_confirm_does_not_create_batch_without_batch_or_expiry(): void
    {
        $receipt = $this->createDraftReceipt(qty: 30, unitCost: 4.00);

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $batchCount = StockBatch::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->count();

        $this->assertEquals(0, $batchCount);
    }

    public function test_confirm_throws_when_already_confirmed(): void
    {
        $receipt = $this->createDraftReceipt(qty: 50, unitCost: 5.00);

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already confirmed');

        $this->service->confirm($receipt->id, $this->store->id, $this->user->id);
    }

    // ═══════════════════════════════════════════════════════════
    // list() / find()
    // ═══════════════════════════════════════════════════════════

    public function test_list_paginates_goods_receipts(): void
    {
        $this->createDraftReceipt(qty: 10, unitCost: 1.00);
        $this->createDraftReceipt(qty: 20, unitCost: 2.00);
        $this->createDraftReceipt(qty: 30, unitCost: 3.00);

        $result = $this->service->list($this->store->id, perPage: 2);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_find_returns_receipt_with_items(): void
    {
        $receipt = $this->createDraftReceipt(qty: 50, unitCost: 5.00);

        $found = $this->service->find($receipt->id, $this->store->id);

        $this->assertEquals($receipt->id, $found->id);
        $this->assertNotEmpty($found->goodsReceiptItems);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function createDraftReceipt(float $qty, float $unitCost): GoodsReceipt
    {
        return $this->service->create(
            data: [
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'received_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity' => $qty, 'unit_cost' => $unitCost],
            ],
        );
    }
}
