<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the SoftPosTransactionResource Filament admin panel page.
 *
 * Covers:
 *  1.  Admin with softpos.view can access the list page
 *  2.  Admin without the permission is forbidden
 *  3.  Unauthenticated user is redirected to login
 *  4.  List page renders a created transaction
 *  5.  View page renders a single transaction record
 *  6.  Create route is forbidden (read-only resource)
 *  7.  Transaction is persisted with all billing fee fields
 *  8.  Fixed fee_type transaction is stored correctly
 *  9.  Multiple transactions for the same store are counted correctly
 * 10.  Refunded transaction status is stored correctly
 */
class SoftPosTransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private AdminUser $adminWithoutPermission;
    private Organization $org;
    private Store $store;
    private Register $register;

    // ─── setUp ────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // ── Permission ────────────────────────────────────────────
        $permission = AdminPermission::forceCreate([
            'name'        => 'softpos.view',
            'group'       => 'softpos',
            'description' => 'View SoftPOS transactions list in admin panel',
        ]);

        // ── Role WITH softpos.view ────────────────────────────────
        $roleWith = AdminRole::forceCreate([
            'name'      => 'SoftPOS Viewer',
            'slug'      => 'softpos_viewer',
            'is_system' => false,
        ]);
        AdminRolePermission::create([
            'admin_role_id'       => $roleWith->id,
            'admin_permission_id' => $permission->id,
        ]);

        $this->admin = AdminUser::forceCreate([
            'name'          => 'SoftPOS Admin',
            'email'         => 'softpos-admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->admin->id,
            'admin_role_id' => $roleWith->id,
            'assigned_at'   => now(),
        ]);

        // ── Role WITHOUT any softpos permission ───────────────────
        $roleWithout = AdminRole::forceCreate([
            'name'      => 'No SoftPOS',
            'slug'      => 'no_softpos',
            'is_system' => false,
        ]);

        $this->adminWithoutPermission = AdminUser::forceCreate([
            'name'          => 'No Perm Admin',
            'email'         => 'no-softpos@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->adminWithoutPermission->id,
            'admin_role_id' => $roleWithout->id,
            'assigned_at'   => now(),
        ]);

        // ── Fixtures ──────────────────────────────────────────────
        $this->org = Organization::create([
            'name'          => 'SoftPOS Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'SoftPOS Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        StoreSettings::create([
            'store_id'                      => $this->store->id,
            'tax_rate'                      => 15,
            'enable_refunds'                => true,
            'enable_exchanges'              => true,
            'return_without_receipt_policy' => 'deny',
            'held_cart_expiry_hours'        => 24,
        ]);

        $this->register = Register::create([
            'store_id'        => $this->store->id,
            'name'            => 'Terminal 1',
            'device_id'       => 'sp-test-001',
            'platform'        => 'android',
            'app_version'     => '1.0.0',
            'is_active'       => true,
            'softpos_enabled' => true,
            'softpos_status'  => 'active',
            'nfc_capable'     => true,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function makeSoftPosTransaction(array $overrides = []): SoftPosTransaction
    {
        return SoftPosTransaction::create(array_merge([
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'terminal_id'     => $this->register->id,
            'amount'          => 100.0,
            'currency'        => 'SAR',
            'payment_method'  => 'mada',
            'status'          => 'completed',
            'platform_fee'    => 0.600,
            'gateway_fee'     => 0.400,
            'margin'          => 0.200,
            'fee_type'        => 'percentage',
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════
    // 1 — Access Control: authorized admin
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_with_softpos_view_can_access_list_page(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/soft-pos-transactions')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // 2 — Access Control: no permission
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_without_permission_cannot_access_list_page(): void
    {
        $this->actingAs($this->adminWithoutPermission, 'admin')
            ->get('/admin/soft-pos-transactions')
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════
    // 3 — Access Control: unauthenticated
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/admin/soft-pos-transactions')
            ->assertRedirect('/admin/login');
    }

    // ═══════════════════════════════════════════════════════════════
    // 4 — List page renders a transaction
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_list_page_renders_created_transaction(): void
    {
        $txn = $this->makeSoftPosTransaction([
            'payment_method'  => 'visa',
            'amount'          => 250.0,
            'transaction_ref' => 'REF-LIST-001',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/soft-pos-transactions')
            ->assertOk()
            ->assertSee($txn->id);
    }

    // ═══════════════════════════════════════════════════════════════
    // 5 — View page renders a single record
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_view_page_renders_transaction_details(): void
    {
        $txn = $this->makeSoftPosTransaction([
            'transaction_ref' => 'TXN-VIEW-999',
            'payment_method'  => 'mada',
            'status'          => 'completed',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/soft-pos-transactions/{$txn->id}")
            ->assertOk()
            ->assertSee('TXN-VIEW-999');
    }

    // ═══════════════════════════════════════════════════════════════
    // 6 — Create route is disabled
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_create_route_is_not_accessible(): void
    {
        // Create page is not registered in getPages(), so the route does not exist.
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/soft-pos-transactions/create')
            ->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // 7 — Model persistence: all fee fields
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_softpos_transaction_is_persisted_with_all_fee_fields(): void
    {
        $txn = $this->makeSoftPosTransaction([
            'amount'          => 500.0,
            'platform_fee'    => 3.0,
            'gateway_fee'     => 2.0,
            'margin'          => 1.0,
            'fee_type'        => 'percentage',
            'payment_method'  => 'mada',
            'status'          => 'completed',
            'transaction_ref' => 'TXN-FEE-001',
        ]);

        $this->assertDatabaseHas('softpos_transactions', [
            'id'              => $txn->id,
            'amount'          => '500.000',
            'platform_fee'    => '3.000',
            'gateway_fee'     => '2.000',
            'margin'          => '1.000',
            'fee_type'        => 'percentage',
            'payment_method'  => 'mada',
            'status'          => 'completed',
            'transaction_ref' => 'TXN-FEE-001',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 8 — Model persistence: fixed fee_type (Visa/MC)
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_softpos_transaction_with_fixed_fee_type_is_persisted(): void
    {
        $txn = $this->makeSoftPosTransaction([
            'payment_method' => 'visa',
            'platform_fee'   => 1.0,
            'gateway_fee'    => 0.5,
            'margin'         => 0.5,
            'fee_type'       => 'fixed',
        ]);

        $this->assertDatabaseHas('softpos_transactions', [
            'id'             => $txn->id,
            'payment_method' => 'visa',
            'platform_fee'   => '1.000',
            'gateway_fee'    => '0.500',
            'margin'         => '0.500',
            'fee_type'       => 'fixed',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 9 — Multiple transactions counted correctly
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_multiple_transactions_for_same_store_are_counted_correctly(): void
    {
        $this->makeSoftPosTransaction(['payment_method' => 'mada',       'status' => 'completed']);
        $this->makeSoftPosTransaction(['payment_method' => 'visa',       'status' => 'completed']);
        $this->makeSoftPosTransaction(['payment_method' => 'mastercard', 'status' => 'pending']);

        $this->assertEquals(
            3,
            SoftPosTransaction::where('store_id', $this->store->id)->count(),
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // 10 — Refunded transaction status
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_refunded_transaction_status_is_stored_correctly(): void
    {
        $txn = $this->makeSoftPosTransaction([
            'status'         => 'refunded',
            'payment_method' => 'amex',
        ]);

        $this->assertDatabaseHas('softpos_transactions', [
            'id'     => $txn->id,
            'status' => 'refunded',
        ]);
    }
}
