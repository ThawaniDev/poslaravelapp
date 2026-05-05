<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Supplier;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests that the CheckPermission middleware correctly enforces
 * role-based access control on every inventory endpoint.
 *
 * IMPORTANT PATTERN: Use withToken() for the "forbidden" assertion
 * only. Use actingAs($user, 'sanctum') for the "allowed" assertion.
 * Reason: Sanctum's auth guard singleton retains user state between
 * consecutive withToken() calls in the same test method, causing the
 * second call to resolve the first user instead of the second.
 */
class InventoryPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $owner;
    private User $noPerms;
    private User $viewUser;
    private User $adjustUser;
    private User $manageUser;
    private User $receiveUser;
    private User $transferUser;
    private User $purchaseOrderUser;
    private User $recipesUser;
    private User $stocktakeUser;
    private User $writeOffUser;
    private User $supplierReturnUser;

    private string $ownerToken;
    private string $noPermsToken;

    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->aliasMiddleware('permission', CheckPermission::class);
        app(PermissionService::class)->seedAll();

        $this->org = Organization::create([
            'name' => 'Inventory Perm Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Perm Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-inv-perm',
            'is_active' => true,
            'monthly_price' => 49.00,
            'annual_price' => 490.00,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Product',
            'sell_price' => 10.00,
            'sync_version' => 1,
        ]);

        $this->supplier = Supplier::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Supplier',
            'is_active' => true,
        ]);

        $this->owner = $this->makeUser('owner@inv-perm.test', 'owner');
        $this->noPerms = $this->makeUser('noperms@inv-perm.test', 'cashier');
        $this->viewUser = $this->makeUser('view@inv-perm.test', 'cashier');
        $this->adjustUser = $this->makeUser('adjust@inv-perm.test', 'cashier');
        $this->manageUser = $this->makeUser('manage@inv-perm.test', 'cashier');
        $this->receiveUser = $this->makeUser('receive@inv-perm.test', 'cashier');
        $this->transferUser = $this->makeUser('transfer@inv-perm.test', 'cashier');
        $this->purchaseOrderUser = $this->makeUser('po@inv-perm.test', 'cashier');
        $this->recipesUser = $this->makeUser('recipes@inv-perm.test', 'cashier');
        $this->stocktakeUser = $this->makeUser('stocktake@inv-perm.test', 'cashier');
        $this->writeOffUser = $this->makeUser('writeoff@inv-perm.test', 'cashier');
        $this->supplierReturnUser = $this->makeUser('supplierreturn@inv-perm.test', 'cashier');

        $this->grantPermissions($this->viewUser, ['inventory.view']);
        $this->grantPermissions($this->adjustUser, ['inventory.adjust']);
        $this->grantPermissions($this->manageUser, ['inventory.manage']);
        $this->grantPermissions($this->receiveUser, ['inventory.receive']);
        $this->grantPermissions($this->transferUser, ['inventory.transfer']);
        $this->grantPermissions($this->purchaseOrderUser, ['inventory.purchase_orders']);
        $this->grantPermissions($this->recipesUser, ['inventory.recipes']);
        $this->grantPermissions($this->stocktakeUser, ['inventory.stocktake']);
        $this->grantPermissions($this->writeOffUser, ['inventory.write_off']);
        $this->grantPermissions($this->supplierReturnUser, ['inventory.supplier_returns']);

        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->noPermsToken = $this->noPerms->createToken('test')->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════
    // Unauthenticated
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_request_gets_401(): void
    {
        $this->getJson('/api/v2/inventory/stock-levels')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/goods-receipts')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/stock-adjustments')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/stock-transfers')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/purchase-orders')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/recipes')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/stocktakes')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/waste-records')->assertUnauthorized();
        $this->getJson('/api/v2/inventory/supplier-returns')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.view
    // ═══════════════════════════════════════════════════════════

    public function test_stock_levels_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/stock-levels')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'inventory.view');
    }

    public function test_stock_levels_allowed_with_inventory_view(): void
    {
        $this->actingAs($this->viewUser, 'sanctum')
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_stock_movements_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/stock-movements')
            ->assertForbidden();
    }

    public function test_stock_movements_allowed_with_inventory_view(): void
    {
        $this->actingAs($this->viewUser, 'sanctum')
            ->getJson('/api/v2/inventory/stock-movements?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_expiry_alerts_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/expiry-alerts')
            ->assertForbidden();
    }

    public function test_expiry_alerts_allowed_with_inventory_view(): void
    {
        $this->actingAs($this->viewUser, 'sanctum')
            ->getJson('/api/v2/inventory/expiry-alerts?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_low_stock_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/low-stock')
            ->assertForbidden();
    }

    public function test_low_stock_allowed_with_inventory_view(): void
    {
        $this->actingAs($this->viewUser, 'sanctum')
            ->getJson('/api/v2/inventory/low-stock?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.manage
    // ═══════════════════════════════════════════════════════════

    public function test_set_reorder_point_forbidden_without_manage_permission(): void
    {
        $levelId = \App\Domain\Inventory\Models\StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'sync_version' => 1,
        ])->id;

        $this->withToken($this->noPermsToken)
            ->putJson("/api/v2/inventory/stock-levels/{$levelId}/reorder-point", ['reorder_point' => 10])
            ->assertForbidden();
    }

    public function test_set_reorder_point_allowed_with_manage_permission(): void
    {
        $levelId = \App\Domain\Inventory\Models\StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'sync_version' => 1,
        ])->id;

        $this->actingAs($this->manageUser, 'sanctum')
            ->putJson("/api/v2/inventory/stock-levels/{$levelId}/reorder-point", ['reorder_point' => 10])
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.adjust
    // ═══════════════════════════════════════════════════════════

    public function test_stock_adjustments_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/stock-adjustments')
            ->assertForbidden();
    }

    public function test_stock_adjustments_allowed_with_adjust_permission(): void
    {
        $this->actingAs($this->adjustUser, 'sanctum')
            ->getJson('/api/v2/inventory/stock-adjustments?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.receive
    // ═══════════════════════════════════════════════════════════

    public function test_goods_receipts_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/goods-receipts')
            ->assertForbidden();
    }

    public function test_goods_receipts_allowed_with_receive_permission(): void
    {
        $this->actingAs($this->receiveUser, 'sanctum')
            ->getJson('/api/v2/inventory/goods-receipts?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.transfer
    // ═══════════════════════════════════════════════════════════

    public function test_stock_transfers_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/stock-transfers')
            ->assertForbidden();
    }

    public function test_stock_transfers_allowed_with_transfer_permission(): void
    {
        $this->actingAs($this->transferUser, 'sanctum')
            ->getJson('/api/v2/inventory/stock-transfers?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.purchase_orders
    // ═══════════════════════════════════════════════════════════

    public function test_purchase_orders_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/purchase-orders')
            ->assertForbidden();
    }

    public function test_purchase_orders_allowed_with_po_permission(): void
    {
        $this->actingAs($this->purchaseOrderUser, 'sanctum')
            ->getJson('/api/v2/inventory/purchase-orders?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.recipes
    // ═══════════════════════════════════════════════════════════

    public function test_recipes_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/recipes')
            ->assertForbidden();
    }

    public function test_recipes_allowed_with_recipes_permission(): void
    {
        $this->actingAs($this->recipesUser, 'sanctum')
            ->getJson('/api/v2/inventory/recipes?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.stocktake
    // ═══════════════════════════════════════════════════════════

    public function test_stocktakes_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/stocktakes')
            ->assertForbidden();
    }

    public function test_stocktakes_allowed_with_stocktake_permission(): void
    {
        $this->actingAs($this->stocktakeUser, 'sanctum')
            ->getJson('/api/v2/inventory/stocktakes?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.write_off
    // ═══════════════════════════════════════════════════════════

    public function test_waste_records_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/waste-records')
            ->assertForbidden();
    }

    public function test_waste_records_allowed_with_write_off_permission(): void
    {
        $this->actingAs($this->writeOffUser, 'sanctum')
            ->getJson('/api/v2/inventory/waste-records?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // inventory.supplier_returns
    // ═══════════════════════════════════════════════════════════

    public function test_supplier_returns_forbidden_without_permission(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/supplier-returns')
            ->assertForbidden();
    }

    public function test_supplier_returns_allowed_with_supplier_returns_permission(): void
    {
        $this->actingAs($this->supplierReturnUser, 'sanctum')
            ->getJson('/api/v2/inventory/supplier-returns?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Owner bypasses all permission checks
    // ═══════════════════════════════════════════════════════════

    public function test_owner_can_access_stock_levels(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/inventory/stock-levels?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_owner_can_access_goods_receipts(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/inventory/goods-receipts?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_owner_can_access_stock_adjustments(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/inventory/stock-adjustments?store_id=' . $this->store->id)
            ->assertOk();
    }

    public function test_owner_can_access_purchase_orders(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/inventory/purchase-orders?store_id=' . $this->store->id)
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Cross-permission checks
    // ═══════════════════════════════════════════════════════════

    public function test_view_permission_cannot_access_write_off_endpoint(): void
    {
        $this->actingAs($this->viewUser, 'sanctum')
            ->getJson('/api/v2/inventory/waste-records')
            ->assertForbidden();
    }

    public function test_adjust_permission_cannot_access_goods_receipts(): void
    {
        $this->actingAs($this->adjustUser, 'sanctum')
            ->getJson('/api/v2/inventory/goods-receipts')
            ->assertForbidden();
    }

    public function test_transfer_permission_cannot_access_stocktakes(): void
    {
        $this->actingAs($this->transferUser, 'sanctum')
            ->getJson('/api/v2/inventory/stocktakes')
            ->assertForbidden();
    }

    public function test_forbidden_response_includes_required_permissions_field(): void
    {
        $this->withToken($this->noPermsToken)
            ->getJson('/api/v2/inventory/stock-levels')
            ->assertForbidden()
            ->assertJsonStructure(['required_permissions'])
            ->assertJsonPath('required_permissions.0', 'inventory.view');
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function grantPermissions(User $user, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        $roleName = 'inv_auto_' . substr(md5(implode(',', $permissionNames)), 0, 8);

        $role = Role::firstOrCreate(
            ['name' => $roleName, 'store_id' => $this->store->id],
            ['display_name' => 'Auto Role', 'guard_name' => 'staff', 'is_predefined' => false],
        );

        $role->permissions()->syncWithoutDetaching($permissionIds);

        DB::table('model_has_roles')->updateOrInsert([
            'role_id' => $role->id,
            'model_id' => $user->id,
            'model_type' => get_class($user),
        ]);
    }
}
