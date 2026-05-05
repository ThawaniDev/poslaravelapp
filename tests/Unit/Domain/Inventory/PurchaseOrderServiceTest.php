<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for PurchaseOrderService.
 *
 * Covers:
 *  - create() returns Draft PO with items and total_cost
 *  - send() advances from Draft to Sent
 *  - send() throws when not Draft
 *  - receive() adds stock levels
 *  - receive() recalculates WAC
 *  - receive() advances to PartiallyReceived / FullyReceived
 *  - receive() caps at quantity_ordered (no over-receive)
 *  - receive() per-line idempotency prevents double stock
 *  - cancel() on Draft succeeds
 *  - cancel() on Sent succeeds
 *  - cancel() on FullyReceived throws
 */
class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private Organization $org;
    private Store $store;
    private Product $product;
    private Product $product2;
    private Supplier $supplier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PurchaseOrderService::class);

        $this->org = Organization::create([
            'name' => 'PO Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'PO Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Flour 1kg',
            'sell_price' => 4.00,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Oil 1L',
            'sell_price' => 6.00,
            'sync_version' => 1,
        ]);

        $this->supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Bulk Foods Ltd.',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Purchaser',
            'email' => 'purchaser@po.test',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // create()
    // ═══════════════════════════════════════════════════════════

    public function test_create_returns_draft_po_with_items(): void
    {
        $po = $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'reference_number' => 'PO-001',
                'created_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity_ordered' => 100, 'unit_cost' => 2.00],
            ],
        );

        $this->assertEquals(PurchaseOrderStatus::Draft, $po->status);
        $this->assertEquals('PO-001', $po->reference_number);
        $this->assertCount(1, $po->purchaseOrderItems);
    }

    public function test_create_calculates_total_cost(): void
    {
        $po = $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'created_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity_ordered' => 50, 'unit_cost' => 3.00],  // 150
                ['product_id' => $this->product2->id, 'quantity_ordered' => 20, 'unit_cost' => 5.00], // 100
            ],
        );

        $this->assertEqualsWithDelta(250.0, (float) $po->total_cost, 0.001);
    }

    // ═══════════════════════════════════════════════════════════
    // send()
    // ═══════════════════════════════════════════════════════════

    public function test_send_advances_draft_to_sent(): void
    {
        $po = $this->createDraftPo(100, 2.00);

        $sent = $this->service->send($po->id, $this->store->id);

        $this->assertEquals(PurchaseOrderStatus::Sent, $sent->status);
    }

    public function test_send_throws_when_not_draft(): void
    {
        $po = $this->createDraftPo(100, 2.00);
        $this->service->send($po->id, $this->store->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('draft');

        $this->service->send($po->id, $this->store->id);
    }

    // ═══════════════════════════════════════════════════════════
    // receive()
    // ═══════════════════════════════════════════════════════════

    public function test_receive_adds_stock_level(): void
    {
        $po = $this->createSentPo(100, 3.00);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 100],
        ]);

        $qty = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(100.0, $qty);
    }

    public function test_receive_recalculates_wac(): void
    {
        $po = $this->createSentPo(100, 6.00);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 100],
        ]);

        $avgCost = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('average_cost');

        $this->assertEqualsWithDelta(6.00, $avgCost, 0.001);
    }

    public function test_receive_partial_advances_to_partially_received(): void
    {
        $po = $this->createSentPo(100, 2.00);

        $updated = $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 60],
        ]);

        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived, $updated->status);
    }

    public function test_receive_full_advances_to_fully_received(): void
    {
        $po = $this->createSentPo(100, 2.00);

        $updated = $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 100],
        ]);

        $this->assertEquals(PurchaseOrderStatus::FullyReceived, $updated->status);
    }

    public function test_receive_two_partial_deliveries_add_up(): void
    {
        $po = $this->createSentPo(100, 2.00);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 60],
        ]);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 40],
        ]);

        $qty = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(100.0, $qty);
    }

    public function test_receive_throws_on_over_receive(): void
    {
        $po = $this->createSentPo(50, 3.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('remaining');

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 100],
        ]);
    }

    public function test_receive_idempotency_prevents_double_stock(): void
    {
        $po = $this->createSentPo(100, 2.00);

        $key = Str::uuid()->toString();

        $this->service->receive(
            $po->id,
            $this->store->id,
            [['product_id' => $this->product->id, 'quantity_received' => 50]],
            $key,
        );

        // Retry same call with same idempotency key
        $this->service->receive(
            $po->id,
            $this->store->id,
            [['product_id' => $this->product->id, 'quantity_received' => 50]],
            $key,
        );

        // Only 50 added, not 100
        $qty = (float) StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEquals(50.0, $qty);
    }

    public function test_receive_throws_when_fully_received(): void
    {
        $po = $this->createSentPo(50, 2.00);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 50],
        ]);

        // PO is now FullyReceived — cannot receive again
        $this->expectException(\RuntimeException::class);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 10],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // cancel()
    // ═══════════════════════════════════════════════════════════

    public function test_cancel_draft_po(): void
    {
        $po = $this->createDraftPo(100, 2.00);

        $cancelled = $this->service->cancel($po->id, $this->store->id);

        $this->assertEquals(PurchaseOrderStatus::Cancelled, $cancelled->status);
    }

    public function test_cancel_sent_po(): void
    {
        $po = $this->createSentPo(100, 2.00);

        $cancelled = $this->service->cancel($po->id, $this->store->id);

        $this->assertEquals(PurchaseOrderStatus::Cancelled, $cancelled->status);
    }

    public function test_cancel_throws_on_fully_received(): void
    {
        $po = $this->createSentPo(50, 2.00);

        $this->service->receive($po->id, $this->store->id, [
            ['product_id' => $this->product->id, 'quantity_received' => 50],
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->cancel($po->id, $this->store->id);
    }

    // ═══════════════════════════════════════════════════════════
    // list() / find()
    // ═══════════════════════════════════════════════════════════

    public function test_list_returns_paginated_pos(): void
    {
        $this->createDraftPo(10, 1.00);
        $this->createDraftPo(20, 2.00);
        $this->createDraftPo(30, 3.00);

        $result = $this->service->list($this->store->id, perPage: 2);

        $this->assertCount(2, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_list_filters_by_status(): void
    {
        $po1 = $this->createDraftPo(10, 1.00);
        $this->service->send($po1->id, $this->store->id);

        $this->createDraftPo(20, 2.00); // stays Draft

        $result = $this->service->list($this->store->id, status: PurchaseOrderStatus::Sent->value);

        $this->assertCount(1, $result->items());
    }

    public function test_find_returns_po_with_items(): void
    {
        $po = $this->createDraftPo(100, 5.00);

        $found = $this->service->find($po->id, $this->store->id);

        $this->assertEquals($po->id, $found->id);
        $this->assertNotEmpty($found->purchaseOrderItems);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function createDraftPo(float $qty, float $unitCost): PurchaseOrder
    {
        return $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'store_id' => $this->store->id,
                'supplier_id' => $this->supplier->id,
                'created_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity_ordered' => $qty, 'unit_cost' => $unitCost],
            ],
        );
    }

    private function createSentPo(float $qty, float $unitCost): PurchaseOrder
    {
        $po = $this->createDraftPo($qty, $unitCost);
        $this->service->send($po->id, $this->store->id);
        return $po->fresh();
    }
}
