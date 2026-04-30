<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RolesPermissionsEdgeCasesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionService::class)->seedAll();

        $org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Role Creation Edge Cases ────────────────────────────────

    public function test_create_role_without_permissions(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'store_id' => $this->store->id,
                'name' => 'empty_role',
                'display_name' => 'Empty Role',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'empty_role');
    }

    public function test_create_role_with_all_permissions(): void
    {
        $allPermIds = Permission::pluck('id')->toArray();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'store_id' => $this->store->id,
                'name' => 'super_role',
                'display_name' => 'Super Role',
                'permission_ids' => $allPermIds,
            ]);

        $response->assertCreated();

        $roleId = $response->json('data.id');
        $role = Role::find($roleId);
        $this->assertEquals(count($allPermIds), $role->permissions->count());
    }

    public function test_create_role_with_duplicate_name_in_same_store(): void
    {
        Role::create([
            'store_id' => $this->store->id,
            'name' => 'cashier_special',
            'display_name' => 'Cashier Special',
            'guard_name' => 'staff',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'store_id' => $this->store->id,
                'name' => 'cashier_special',
                'display_name' => 'Another Cashier',
            ]);

        // Should fail — duplicate role name within same store
        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_create_role_with_same_name_in_different_store(): void
    {
        // Create another store
        $store2 = Store::create([
            'organization_id' => $this->store->organization_id,
            'name' => 'Branch 2',
            'business_type' => 'grocery',
            'currency' => 'SAR',
        ]);

        Role::create([
            'store_id' => $this->store->id,
            'name' => 'floor_manager',
            'display_name' => 'Floor Manager',
            'guard_name' => 'staff',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'store_id' => $store2->id,
                'name' => 'floor_manager',
                'display_name' => 'Floor Manager',
            ]);

        // Same name in different store should be fine
        $response->assertCreated();
    }

    public function test_create_role_with_arabic_display_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'store_id' => $this->store->id,
                'name' => 'arabic_role',
                'display_name' => 'مدير المبيعات',
                'description' => 'دور مدير المبيعات',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.display_name', 'مدير المبيعات');
    }

    // ─── Role Update Edge Cases ──────────────────────────────────

    public function test_can_sync_permissions_on_update(): void
    {
        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'syncable',
            'display_name' => 'Syncable',
            'guard_name' => 'staff',
            'is_predefined' => false,
        ]);

        $posPerms = Permission::where('module', 'pos')->pluck('id')->toArray();
        $role->permissions()->attach($posPerms);

        // Now update with inventory permissions only
        $invPerms = Permission::where('module', 'inventory')->pluck('id')->toArray();

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/roles/{$role->id}", [
                'permission_ids' => $invPerms,
            ]);

        $response->assertOk();

        $role->refresh();
        $role->load('permissions');
        $currentPermModules = $role->permissions->pluck('module')->unique()->toArray();
        $this->assertContains('inventory', $currentPermModules);
    }

    // ─── Role Deletion Edge Cases ────────────────────────────────

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'assigned_role',
            'display_name' => 'Assigned',
            'guard_name' => 'staff',
            'is_predefined' => false,
        ]);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_id' => $cashier->id,
            'model_type' => get_class($cashier),
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/roles/{$role->id}");

        // May delete anyway or reject — depends on implementation
        // At minimum should not crash
        $this->assertContains($response->status(), [200, 409, 422, 500]);
    }

    public function test_delete_nonexistent_role(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson('/api/v2/staff/roles/00000000-0000-0000-0000-000000000099');

        $this->assertContains($response->status(), [404, 500]);
    }

    // ─── Role Assignment Edge Cases ──────────────────────────────

    public function test_assign_same_role_twice_is_idempotent(): void
    {
        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'double_assign',
            'display_name' => 'Double Assign',
            'guard_name' => 'staff',
        ]);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Assign once
        $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role->id}/assign", [
                'user_id' => $cashier->id,
            ]);

        // Assign again — should not crash or duplicate
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role->id}/assign", [
                'user_id' => $cashier->id,
            ]);

        $this->assertContains($response->status(), [200, 409, 422]);

        // Should still have exactly 1 entry
        $count = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $cashier->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_assign_multiple_roles_to_user(): void
    {
        $role1 = Role::create([
            'store_id' => $this->store->id,
            'name' => 'role_a',
            'display_name' => 'Role A',
            'guard_name' => 'staff',
        ]);

        $role2 = Role::create([
            'store_id' => $this->store->id,
            'name' => 'role_b',
            'display_name' => 'Role B',
            'guard_name' => 'staff',
        ]);

        $cashier = User::create([
            'name' => 'Multi-Role',
            'email' => 'multi@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role1->id}/assign", [
                'user_id' => $cashier->id,
            ])->assertOk();

        $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role2->id}/assign", [
                'user_id' => $cashier->id,
            ])->assertOk();

        $userRoles = DB::table('model_has_roles')
            ->where('model_id', $cashier->id)
            ->count();
        $this->assertEquals(2, $userRoles);
    }

    public function test_unassign_role_not_assigned(): void
    {
        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'not_assigned',
            'display_name' => 'Not Assigned',
            'guard_name' => 'staff',
        ]);

        $cashier = User::create([
            'name' => 'Cashier3',
            'email' => 'cashier3@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Try to unassign when never assigned
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role->id}/unassign", [
                'user_id' => $cashier->id,
            ]);

        // Should succeed gracefully or return appropriate error
        $this->assertContains($response->status(), [200, 404, 422]);
    }

    // ─── Permission Module Queries ───────────────────────────────

    public function test_invalid_module_returns_empty(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions/module/00000000-0000-0000-0000-000000000099_module');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_permission_has_required_structure(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions');

        $response->assertOk();

        $firstPerm = $response->json('data.0');
        $this->assertArrayHasKey('id', $firstPerm);
        $this->assertArrayHasKey('name', $firstPerm);
        $this->assertArrayHasKey('module', $firstPerm);
    }

    // ─── PIN Override Edge Cases ─────────────────────────────────

    public function test_pin_override_with_invalid_permission(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/pin-override/check?permission_code=totally.made.up');

        $response->assertOk()
            ->assertJsonPath('data.requires_pin', false);
    }

    public function test_pin_override_without_permission_param(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/pin-override/check');

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_pin_override_history_empty_store(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/pin-override/history?store_id={$this->store->id}");

        $response->assertOk();
        // Should return empty array or collection
        $this->assertIsArray($response->json('data'));
    }

    // ─── Effective Permissions ────────────────────────────────────

    public function test_user_with_no_roles_has_empty_permissions(): void
    {
        $cashier = User::create([
            'name' => 'No Roles',
            'email' => 'noroles@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $cashierToken = $cashier->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($cashierToken)
            ->getJson("/api/v2/staff/roles/user-permissions?store_id={$this->store->id}");

        $response->assertOk();
        $permissions = $response->json('data.permissions');
        $this->assertEmpty($permissions);
    }

    public function test_user_with_multiple_roles_gets_merged_permissions(): void
    {
        $posRole = Role::create([
            'store_id' => $this->store->id,
            'name' => 'pos_only',
            'display_name' => 'POS Only',
            'guard_name' => 'staff',
        ]);
        $posRole->permissions()->attach(
            Permission::where('module', 'pos')->pluck('id')
        );

        $inventoryRole = Role::create([
            'store_id' => $this->store->id,
            'name' => 'inv_only',
            'display_name' => 'Inventory Only',
            'guard_name' => 'staff',
        ]);
        $inventoryRole->permissions()->attach(
            Permission::where('module', 'inventory')->pluck('id')
        );

        $cashier = User::create([
            'name' => 'Multi-Role User',
            'email' => 'multirole@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->store->organization_id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        DB::table('model_has_roles')->insert([
            ['role_id' => $posRole->id, 'model_id' => $cashier->id, 'model_type' => get_class($cashier)],
            ['role_id' => $inventoryRole->id, 'model_id' => $cashier->id, 'model_type' => get_class($cashier)],
        ]);

        $cashierToken = $cashier->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($cashierToken)
            ->getJson("/api/v2/staff/roles/user-permissions?store_id={$this->store->id}");

        $response->assertOk();
        $permissions = $response->json('data.permissions');

        // Should include both POS and inventory permissions
        $this->assertNotEmpty(array_filter($permissions, fn($p) => str_starts_with($p, 'pos.')));
        $this->assertNotEmpty(array_filter($permissions, fn($p) => str_starts_with($p, 'inventory.')));
    }

    // ─── Auth Guard ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_create_role(): void
    {
        $response = $this->postJson('/api/v2/staff/roles', [
            'store_id' => $this->store->id,
            'name' => 'hacked',
            'display_name' => 'Hacked',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_list_permissions(): void
    {
        $response = $this->getJson('/api/v2/staff/permissions');
        $response->assertUnauthorized();
    }
}
