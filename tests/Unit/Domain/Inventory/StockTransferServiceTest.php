<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\StockTransferStatus;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for StockTransferService — 2-phase commit across stores.
 *
 * Covers:
 *  - create() → Pending status with items
 *  - approve() → InTransit, reserves source stock
 *  - approve() throws when not enough available stock
 *  - approve() throws when not Pending
 *  - receive() → Completed, deducts source, credits destination
 *  - receive() handles partial delivery (variance)
 *  - receive() throws when not InTransit
 *  - receive() throws over-receive
 *  - cancel() on Pending succeeds
 *  - cancel() on InTransit throws (code path blocked in service)
 *  - cancel() on Completed throws
 */
class StockTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockTransferService $service;
    private Organization $org;
    private Store $storeA;
    private Store $storeB;
    private Product $product;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockTransferService::class);

        $this->org = Organization::create([
            'name' => 'Transfer Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->storeA = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Store Alpha',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->storeB = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Store Beta',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Widget',
            'sell_price' => 8.00,
            'sync_version' => 1,
        ]);

        $this->user = User::create([
            'name' => 'Warehouse',
            'email' => 'wh@transfer.test',
            'password_hash' => bcrypt('pass'),
            'store_id' => $this->storeA->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // create()
    // ═══════════════════════════════════════════════════════════

    public function test_create_makes_pending_transfer_with_items(): void
    {
        $transfer = $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'created_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity_sent' => 30],
            ],
        );

        $this->assertEquals(StockTransferStatus::Pending, $transfer->status);
        $this->assertEquals($this->storeA->id, $transfer->from_store_id);
        $this->assertEquals($this->storeB->id, $transfer->to_store_id);
        $this->assertCount(1, $transfer->stockTransferItems);
        $this->assertEquals(30.0, (float) $transfer->stockTransferItems->first()->quantity_sent);
    }

    // ═══════════════════════════════════════════════════════════
    // approve()
    // ═══════════════════════════════════════════════════════════

    public function test_approve_reserves_source_stock(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);

        $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        $level = StockLevel::where('store_id', $this->storeA->id)
            ->where('product_id', $this->product->id)->first();

        $this->assertEquals(30.0, (float) $level->reserved_quantity);
        $this->assertEquals(100.0, (float) $level->quantity); // on-hand unchanged
    }

    public function test_approve_changes_status_to_in_transit(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);

        $approved = $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        $this->assertEquals(StockTransferStatus::InTransit, $approved->status);
        $this->assertEquals($this->user->id, $approved->approved_by);
    }

    public function test_approve_throws_when_insufficient_available_stock(): void
    {
        $this->seedStockLevel($this->storeA->id, 10, reserved: 8); // only 2 available

        $transfer = $this->createTransfer(5); // needs 5

        $this->expectException(\RuntimeException::class);

        $this->service->approve($transfer->id, $this->org->id, $this->user->id);
    }

    public function test_approve_throws_when_not_pending(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);

        // Approve first time
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        // Try to approve again → should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pending');

        $this->service->approve($transfer->id, $this->org->id, $this->user->id);
    }

    // ═══════════════════════════════════════════════════════════
    // receive()
    // ═══════════════════════════════════════════════════════════

    public function test_receive_deducts_source_and_credits_destination(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        $this->service->receive($transfer->id, $this->org->id, $this->user->id);

        $sourceQty = (float) StockLevel::where('store_id', $this->storeA->id)
            ->where('product_id', $this->product->id)->value('quantity');

        $destQty = (float) StockLevel::where('store_id', $this->storeB->id)
            ->where('product_id', $this->product->id)->value('quantity');

        $this->assertEquals(70.0, $sourceQty);   // 100 - 30
        $this->assertEquals(30.0, $destQty);      // 0 + 30
    }

    public function test_receive_marks_transfer_completed(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);
        $received = $this->service->receive($transfer->id, $this->org->id, $this->user->id);

        $this->assertEquals(StockTransferStatus::Completed, $received->status);
    }

    public function test_receive_handles_partial_delivery_with_variance(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        // Only 25 of 30 arrived
        $received = $this->service->receive(
            $transfer->id,
            $this->org->id,
            $this->user->id,
            [['product_id' => $this->product->id, 'quantity_received' => 25]],
        );

        $item = $received->stockTransferItems->first();
        $this->assertEquals(25.0, (float) $item->quantity_received);
        $this->assertEquals(5.0, (float) $item->variance_qty);

        // Source loses full sent qty (30), destination gets only received qty (25)
        $sourceQty = (float) StockLevel::where('store_id', $this->storeA->id)
            ->where('product_id', $this->product->id)->value('quantity');
        $destQty = (float) StockLevel::where('store_id', $this->storeB->id)
            ->where('product_id', $this->product->id)->value('quantity');

        $this->assertEquals(70.0, $sourceQty); // loses 30
        $this->assertEquals(25.0, $destQty);   // only 25 credited
    }

    public function test_receive_throws_on_over_receive(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only 30 were sent');

        $this->service->receive(
            $transfer->id,
            $this->org->id,
            $this->user->id,
            [['product_id' => $this->product->id, 'quantity_received' => 50]],
        );
    }

    public function test_receive_throws_when_not_in_transit(): void
    {
        $transfer = $this->createTransfer(30);
        // Still Pending — skip approve

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('in-transit');

        $this->service->receive($transfer->id, $this->org->id, $this->user->id);
    }

    // ═══════════════════════════════════════════════════════════
    // cancel()
    // ═══════════════════════════════════════════════════════════

    public function test_cancel_pending_transfer_succeeds(): void
    {
        $transfer = $this->createTransfer(30);

        $cancelled = $this->service->cancel($transfer->id, $this->org->id);

        $this->assertEquals(StockTransferStatus::Cancelled, $cancelled->status);
    }

    public function test_cancel_in_transit_transfer_is_blocked_by_current_logic(): void
    {
        // NOTE: The current cancel() implementation only allows Pending.
        // InTransit cancel is unreachable dead code (the throw comes after the
        // status check that already blocks it). This test documents that fact.
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);

        // Transfer is now InTransit
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pending');

        $this->service->cancel($transfer->id, $this->org->id);
    }

    public function test_cancel_completed_transfer_throws(): void
    {
        $this->seedStockLevel($this->storeA->id, 100);

        $transfer = $this->createTransfer(30);
        $this->service->approve($transfer->id, $this->org->id, $this->user->id);
        $this->service->receive($transfer->id, $this->org->id, $this->user->id);

        $this->expectException(\RuntimeException::class);

        $this->service->cancel($transfer->id, $this->org->id);
    }

    // ═══════════════════════════════════════════════════════════
    // list() / find()
    // ═══════════════════════════════════════════════════════════

    public function test_list_returns_transfers_for_organization(): void
    {
        $this->createTransfer(10);
        $this->createTransfer(20);

        $result = $this->service->list($this->org->id);

        $this->assertEquals(2, $result->total());
    }

    public function test_find_returns_transfer_with_items(): void
    {
        $transfer = $this->createTransfer(15);

        $found = $this->service->find($this->org->id, $transfer->id);

        $this->assertEquals($transfer->id, $found->id);
        $this->assertNotEmpty($found->stockTransferItems);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function createTransfer(float $qty): StockTransfer
    {
        return $this->service->create(
            data: [
                'organization_id' => $this->org->id,
                'from_store_id' => $this->storeA->id,
                'to_store_id' => $this->storeB->id,
                'created_by' => $this->user->id,
            ],
            items: [
                ['product_id' => $this->product->id, 'quantity_sent' => $qty],
            ],
        );
    }

    private function seedStockLevel(string $storeId, float $quantity, float $reserved = 0): StockLevel
    {
        return StockLevel::create([
            'store_id' => $storeId,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
            'average_cost' => 0,
            'sync_version' => 1,
        ]);
    }
}
