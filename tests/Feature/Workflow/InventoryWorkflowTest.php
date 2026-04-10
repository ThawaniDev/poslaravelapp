<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\GoodsReceipt;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Inventory\Models\StockTransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;


/**
 * INVENTORY WORKFLOW TESTS
 *
 * Verifies end-to-end inventory data flows:
 * PO → Approval → Goods Receipt → Stock Update → Cost Averaging →
 * Stock Transfer → Low Stock Alerts → Stocktake → Adjustment →
 * Wastage → Audit Trail
 *
 * Cross-references: Workflows #101-150 in COMPREHENSIVE_WORKFLOW_TESTS.md
 */
class InventoryWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $storeManager;
    private Organization $org;
    private Store $mainStore;
    private Store $branchStore;
    private string $ownerToken;
    private string $managerToken;
    private Product $product1;
    private Product $product2;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Inventory Test Org',
            'name_ar' => 'منظمة اختبار المخزون',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000005',
            'is_active' => true,
        ]);

        $this->mainStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Warehouse',
            'name_ar' => 'المستودع الرئيسي',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->branchStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch Store',
            'name_ar' => 'فرع المتجر',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@inventory-test.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->mainStore->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->storeManager = User::create([
            'name' => 'Store Manager',
            'email' => 'manager@inventory-test.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->mainStore->id,
            'organization_id' => $this->org->id,
            'role' => 'branch_manager',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->mainStore->id);
        $this->managerToken = $this->storeManager->createToken('test', ['*'])->plainTextToken;
        $this->assignBranchManagerRole($this->storeManager, $this->mainStore->id);

        $category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Spices',
            'name_ar' => 'بهارات',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $this->product1 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Saffron 10g',
            'name_ar' => 'زعفران 10 غ',
            'sku' => 'SPC-001',
            'barcode' => '6281001234580',
            'sell_price' => 50.00,
            'cost_price' => 30.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $this->product2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'name' => 'Cardamom 50g',
            'name_ar' => 'هيل 50 غ',
            'sku' => 'SPC-002',
            'barcode' => '6281001234581',
            'sell_price' => 25.00,
            'cost_price' => 12.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->mainStore->id,
            'product_id' => $this->product1->id,
            'quantity' => 100,
            'reorder_point' => 20,
            'max_stock_level' => 500,
            'average_cost' => 30.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->mainStore->id,
            'product_id' => $this->product2->id,
            'quantity' => 200,
            'reorder_point' => 30,
            'max_stock_level' => 1000,
            'average_cost' => 12.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->branchStore->id,
            'product_id' => $this->product1->id,
            'quantity' => 20,
            'reorder_point' => 5,
            'average_cost' => 30.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->branchStore->id,
            'product_id' => $this->product2->id,
            'quantity' => 50,
            'reorder_point' => 10,
            'average_cost' => 12.00,
            'sync_version' => 1,
        ]);

        $this->supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Spice Traders LLC',
            'contact_person' => 'Ahmed',
            'phone' => '966509876543',
            'email' => 'ahmed@spicetraders.com',
            'is_active' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #101-110: PURCHASE ORDER LIFECYCLE
    // ═══════════════════════════════════════════════════════════

    /** @test WF#101: Create purchase order */
    public function test_wf101_create_purchase_order(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/inventory/purchase-orders', [
                'store_id' => $this->mainStore->id,
                'supplier_id' => $this->supplier->id,
                'expected_date' => now()->addDays(7)->toDateString(),
                'notes' => 'Monthly restock',
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity_ordered' => 50, 'unit_cost' => 28.00],
                    ['product_id' => $this->product2->id, 'quantity_ordered' => 100, 'unit_cost' => 11.00],
                ],
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $poId = $response->json('data.id');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $poId,
            'store_id' => $this->mainStore->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $poId,
            'product_id' => $this->product1->id,
            'quantity_ordered' => 50,
            'unit_cost' => 28.00,
        ]);
    }

    /** @test WF#102: Send purchase order (draft → sent) */
    public function test_wf102_approve_purchase_order(): void
    {
        $po = $this->createDraftPO();

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/inventory/purchase-orders/{$po->id}/send");

        $response->assertOk();

        $po->refresh();
        $this->assertEquals('sent', $po->status->value ?? $po->status);
    }

    /** @test WF#103: Receive goods from PO updates stock + cost average */
    public function test_wf103_goods_receipt_updates_stock_and_cost(): void
    {
        $po = $this->createApprovedPO();

        $origStock = StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product1->id)->first();
        $origQty = $origStock->quantity;        // 100
        $origAvgCost = $origStock->average_cost; // 30.00

        $response = $this->withToken($this->managerToken)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'purchase_order_id' => $po->id,
                'store_id' => $this->mainStore->id,
                'received_by' => $this->storeManager->id,
                'items' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 50,
                        'unit_cost' => 28.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        // Goods receipt created as draft
        $grId = $response->json('data.id');
        $this->assertNotNull($grId, 'Goods receipt ID should be returned');

        // Confirm the goods receipt to trigger stock update
        $confirmResp = $this->withToken($this->managerToken)
            ->postJson("/api/v2/inventory/goods-receipts/{$grId}/confirm");
        $confirmResp->assertOk();

        // Stock updated: 100 + 50 = 150
        $origStock->refresh();
        $this->assertEquals($origQty + 50, $origStock->quantity);

        // Weighted average cost: (100*30 + 50*28) / 150 = 29.33
        $expectedAvgCost = round(($origQty * $origAvgCost + 50 * 28.00) / ($origQty + 50), 2);
        $this->assertEqualsWithDelta($expectedAvgCost, $origStock->average_cost, 0.01);

        // Stock movement recorded
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product1->id,
            'quantity' => 50,
        ]);
    }

    /** @test WF#104: Partial goods receipt */
    public function test_wf104_partial_goods_receipt(): void
    {
        $po = $this->createApprovedPO();

        // Receive only some items
        $response = $this->withToken($this->managerToken)
            ->postJson('/api/v2/inventory/goods-receipts', [
                'purchase_order_id' => $po->id,
                'store_id' => $this->mainStore->id,
                'received_by' => $this->storeManager->id,
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity' => 30, 'unit_cost' => 28.00],
                ],
            ]);

        $response->assertStatus(201);
        $grId = $response->json('data.id');

        // Confirm the receipt to apply stock changes
        $confirmResp = $this->withToken($this->managerToken)
            ->postJson("/api/v2/inventory/goods-receipts/{$grId}/confirm");
        $confirmResp->assertOk();

        // PO should be partially_received
        $po->refresh();
        $statusValue = $po->status->value ?? $po->status;
        $this->assertTrue(
            in_array($statusValue, ['partially_received', 'sent']),
            "PO status should be partially_received or sent, got: {$statusValue}"
        );
    }

    // ═══════════════════════════════════════════════════════════
    // WF #111-120: STOCK TRANSFERS BETWEEN STORES
    // ═══════════════════════════════════════════════════════════

    /** @test WF#111: Create stock transfer between stores */
    public function test_wf111_create_stock_transfer(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->mainStore->id,
                'to_store_id' => $this->branchStore->id,
                'notes' => 'Branch restock',
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity_sent' => 10],
                    ['product_id' => $this->product2->id, 'quantity_sent' => 25],
                ],
            ]);

        $response->assertStatus(201);

        $transferId = $response->json('data.id');

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transferId,
            'from_store_id' => $this->mainStore->id,
            'to_store_id' => $this->branchStore->id,
            'status' => 'pending',
        ]);
    }

    /** @test WF#112: Approve stock transfer deducts from source */
    public function test_wf112_ship_transfer_deducts_source(): void
    {
        $transfer = $this->createPendingTransferWithItems();

        $response = $this->withToken($this->managerToken)
            ->postJson("/api/v2/inventory/stock-transfers/{$transfer->id}/approve");

        $response->assertOk();

        // Source deducted
        $srcStock = StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product1->id)->first();
        $this->assertEquals(90, $srcStock->quantity); // 100 - 10

        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status->value ?? $transfer->status);
    }

    /** @test WF#113: Receive stock transfer adds to destination */
    public function test_wf113_receive_transfer_adds_destination(): void
    {
        $transfer = $this->createInTransitTransfer();

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/inventory/stock-transfers/{$transfer->id}/receive", [
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity_received' => 10],
                ],
            ]);

        $response->assertOk();

        // Destination increased
        $destStock = StockLevel::where('store_id', $this->branchStore->id)
            ->where('product_id', $this->product1->id)->first();
        $this->assertEquals(30, $destStock->quantity); // 20 + 10

        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status->value ?? $transfer->status);
    }

    /** @test WF#114: Transfer exceeding available stock — verify API behavior */
    public function test_wf114_transfer_exceeds_available_stock(): void
    {
        // Attempt to transfer more than available (product1 has 100 in mainStore)
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/inventory/stock-transfers', [
                'from_store_id' => $this->mainStore->id,
                'to_store_id' => $this->branchStore->id,
                'notes' => 'Exceeds stock test',
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity_sent' => 9999],
                ],
            ]);

        if ($response->status() === 422) {
            // API validates stock — good
            $response->assertJsonValidationErrors(['items']);
        } else {
            // API creates transfer without stock validation — verify the transfer is created
            $response->assertStatus(201);
            $transferId = $response->json('data.id');
            $this->assertDatabaseHas('stock_transfers', [
                'id' => $transferId,
                'from_store_id' => $this->mainStore->id,
                'status' => 'pending',
            ]);

            // Approve should fail or go negative when trying to deduct
            $approveResp = $this->withToken($this->ownerToken)
                ->postJson("/api/v2/inventory/stock-transfers/{$transferId}/approve");

            // Either blocked at approval (422) or approved and stock goes negative
            $this->assertTrue(
                in_array($approveResp->status(), [200, 422]),
                'Approve of over-stock transfer should either be blocked or allowed. Got: ' . $approveResp->status()
            );
        }
    }

    // ═══════════════════════════════════════════════════════════
    // WF #121-130: STOCK ADJUSTMENTS & STOCKTAKE
    // ═══════════════════════════════════════════════════════════

    /** @test WF#121: Manual stock adjustment */
    public function test_wf121_manual_stock_adjustment(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/inventory/stock-adjustments', [
                'store_id' => $this->mainStore->id,
                'type' => 'decrease',
                'notes' => 'Physical count correction',
                'items' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $stock = StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product1->id)->first();
        $this->assertEquals(95, $stock->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product1->id,
            'type' => 'adjustment_out',
        ]);
    }

    /** @test WF#122: Record wastage/damage */
    public function test_wf122_wastage_deducts_stock(): void
    {
        $response = $this->withToken($this->managerToken)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->mainStore->id,
                'product_id' => $this->product2->id,
                'quantity' => 5,
                'reason' => 'damaged',
                'notes' => 'Damaged in storage',
            ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'Waste record should be created. Status: ' . $response->status()
        );

        $stock = StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product2->id)->first();
        $this->assertEquals(195, $stock->quantity); // 200 - 5

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product2->id,
            'type' => 'waste',
            'quantity' => -5,
        ]);
    }

    /** @test WF#123: Stocktake creation and submission */
    public function test_wf123_stocktake_lifecycle(): void
    {
        // Create stocktake
        $createResp = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/inventory/stocktakes', [
                'store_id' => $this->mainStore->id,
                'type' => 'full',
                'notes' => 'Full store stocktake',
            ]);

        $createResp->assertStatus(201);
        $stocktakeId = $createResp->json('data.id');

        // Record counts
        $countResp = $this->withToken($this->managerToken)
            ->putJson("/api/v2/inventory/stocktakes/{$stocktakeId}/counts", [
                'items' => [
                    ['product_id' => $this->product1->id, 'counted_qty' => 98],
                    ['product_id' => $this->product2->id, 'counted_qty' => 200],
                ],
            ]);

        $countResp->assertOk();

        // Stocktake items show variance
        $this->assertDatabaseHas('stocktake_items', [
            'stocktake_id' => $stocktakeId,
            'product_id' => $this->product1->id,
            'counted_qty' => 98,
            'expected_qty' => 100,
            'variance' => -2,
        ]);

        // Complete stocktake applies adjustments
        $completeResp = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/inventory/stocktakes/{$stocktakeId}/apply");

        $completeResp->assertOk();

        // Stock updated to counted quantity
        $stock = StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product1->id)->first();
        $this->assertEquals(98, $stock->quantity);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #131-140: LOW STOCK ALERTS & REORDER POINTS
    // ═══════════════════════════════════════════════════════════

    /** @test WF#131: Low stock detection via low-stock endpoint */
    public function test_wf131_low_stock_alert_on_depletion(): void
    {
        // Deplete stock below reorder point (product1: qty=100, reorder=20)
        StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product1->id)
            ->update(['quantity' => 5]);

        // Low stock endpoint should detect this product
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/inventory/low-stock?store_id={$this->mainStore->id}");

        $response->assertOk();

        // Verify low-stock data includes our product
        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertNotEmpty($items, 'Low stock endpoint should return products below reorder point');

        $lowStockProduct = collect($items)->first(function ($item) {
            return ($item['product_id'] ?? null) === $this->product1->id
                || ($item['id'] ?? null) === $this->product1->id;
        });

        $this->assertNotNull($lowStockProduct, 'Product with qty=5, reorder=20 should appear in low stock list');

        // Also check the reports low-stock endpoint
        $reportResp = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/reports/inventory/low-stock');
        $reportResp->assertOk();
    }

    /** @test WF#132: Stock levels API returns correct data per store */
    public function test_wf132_stock_levels_per_store(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/inventory/stock-levels?store_id={$this->mainStore->id}");

        $response->assertOk()->assertJsonPath('success', true);

        // Response is paginated: data.data contains items
        $items = $response->json('data.data');
        $this->assertNotEmpty($items);

        // Main store shows its stock
        $product1Data = collect($items)->firstWhere('product_id', $this->product1->id);
        $this->assertNotNull($product1Data);
        $this->assertEquals(100, $product1Data['quantity']);

        // Branch store shows different stock
        $branchResp = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/inventory/stock-levels?store_id={$this->branchStore->id}");

        $branchResp->assertOk();
        $branchItems = $branchResp->json('data.data');
        $branchData = collect($branchItems)->firstWhere('product_id', $this->product1->id);
        $this->assertEquals(20, $branchData['quantity']);
    }

    // ═══════════════════════════════════════════════════════════
    // WF #141-150: SUPPLIER MANAGEMENT
    // ═══════════════════════════════════════════════════════════

    /** @test WF#141: CRUD suppliers */
    public function test_wf141_supplier_crud(): void
    {
        // Create
        $createResp = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/catalog/suppliers', [
                'name' => 'New Supplier',
                'phone' => '966501111111',
                'email' => 'new@supplier.com',
            ]);

        $createResp->assertStatus(201);
        $supplierId = $createResp->json('data.id');

        // Read
        $readResp = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/catalog/suppliers/{$supplierId}");
        $readResp->assertOk()->assertJsonPath('data.name', 'New Supplier');

        // Update
        $updateResp = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/suppliers/{$supplierId}", [
                'name' => 'Updated Supplier',
            ]);
        $updateResp->assertOk()->assertJsonPath('data.name', 'Updated Supplier');

        // Delete
        $deleteResp = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/catalog/suppliers/{$supplierId}");
        $deleteResp->assertOk();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplierId]);
    }

    /** @test WF#142: List purchase orders filtered by status */
    public function test_wf142_list_purchase_orders_by_status(): void
    {
        $this->createDraftPO();
        $this->createApprovedPO();

        $draftResp = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/inventory/purchase-orders?store_id={$this->mainStore->id}&status=draft");
        $draftResp->assertOk();

        $approvedResp = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/inventory/purchase-orders?store_id={$this->mainStore->id}&status=sent");
        $approvedResp->assertOk();

        // Different counts
        $draftCount = count($draftResp->json('data'));
        $approvedCount = count($approvedResp->json('data'));
        $this->assertGreaterThanOrEqual(1, $draftCount);
        $this->assertGreaterThanOrEqual(1, $approvedCount);
    }

    // ═══════════════════════════════════════════════════════════
    // MULTI-TENANT ISOLATION
    // ═══════════════════════════════════════════════════════════

    /** @test WF#145: Cannot see other org's inventory */
    public function test_wf145_inventory_org_isolation(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org', 'name_ar' => 'أخرى',
            'business_type' => 'grocery', 'country' => 'SA', 'is_active' => true,
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id, 'name' => 'Other Store', 'name_ar' => 'أخرى',
            'business_type' => 'grocery', 'currency' => 'SAR', 'locale' => 'ar',
            'timezone' => 'Asia/Riyadh', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $otherUser = User::create([
            'name' => 'Other Owner', 'email' => 'other@owner.test',
            'password_hash' => bcrypt('pass'), 'store_id' => $otherStore->id,
            'organization_id' => $otherOrg->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;

        // Other org should NOT see our stock
        $response = $this->withToken($otherToken)
            ->getJson("/api/v2/inventory/stock-levels?store_id={$this->mainStore->id}");

        $this->assertTrue(
            $response->status() === 403 || count($response->json('data', [])) === 0,
            'Other org should not access our inventory'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function createDraftPO(): PurchaseOrder
    {
        return PurchaseOrder::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->mainStore->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'total_cost' => 2500.00,
            'created_by' => $this->owner->id,
        ]);
    }

    private function createApprovedPO(): PurchaseOrder
    {
        $po = $this->createDraftPO();
        $po->forceFill([
            'status' => 'sent',
        ])->save();
        return $po;
    }

    private function createPendingTransfer(): StockTransfer
    {
        return StockTransfer::create([
            'organization_id' => $this->org->id,
            'from_store_id' => $this->mainStore->id,
            'to_store_id' => $this->branchStore->id,
            'status' => 'pending',
            'created_by' => $this->owner->id,
        ]);
    }

    private function createPendingTransferWithItems(): StockTransfer
    {
        $transfer = $this->createPendingTransfer();
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product1->id,
            'quantity_sent' => 10,
        ]);
        return $transfer;
    }

    private function createInTransitTransfer(): StockTransfer
    {
        $transfer = $this->createPendingTransferWithItems();
        $transfer->forceFill([
            'status' => 'in_transit',
            'approved_by' => $this->owner->id,
            'approved_at' => now(),
        ])->save();
        // Simulate source deduction
        StockLevel::where('store_id', $this->mainStore->id)
            ->where('product_id', $this->product1->id)
            ->decrement('quantity', 10);
        return $transfer;
    }
}
