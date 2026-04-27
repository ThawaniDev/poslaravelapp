<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions
        app(PermissionService::class)->seedAll();

        // Create org, store, owner
        $org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);

        $this->owner = User::create([
            'name'          => 'Owner',
            'email'         => 'owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id'      => $this->store->id,
            'organization_id' => $org->id,
            'role'          => 'owner',
            'is_active'     => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Permissions ─────────────────────────────────────────────

    public function test_can_list_all_permissions(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);

        $this->assertGreaterThan(50, count($response->json('data')));
    }

    public function test_can_list_permissions_grouped_by_module(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions/grouped');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('pos', $data);
        $this->assertArrayHasKey('orders', $data);
        $this->assertArrayHasKey('inventory', $data);
    }

    public function test_can_list_modules(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions/modules');

        $response->assertOk();

        $modules = $response->json('data');
        $this->assertContains('pos', $modules);
        $this->assertContains('orders', $modules);
    }

    public function test_can_get_permissions_for_module(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions/module/pos');

        $response->assertOk();

        $perms = $response->json('data');
        $this->assertGreaterThan(5, count($perms));
        foreach ($perms as $perm) {
            $this->assertStringStartsWith('pos.', $perm['name']);
        }
    }

    public function test_can_list_pin_protected_permissions(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/permissions/pin-protected');

        $response->assertOk();

        $perms = $response->json('data');
        foreach ($perms as $perm) {
            $this->assertTrue($perm['requires_pin']);
        }
    }

    // ─── Roles CRUD ──────────────────────────────────────────────

    public function test_can_create_custom_role(): void
    {
        $permIds = Permission::where('module', 'pos')->pluck('id')->toArray();

        $response = $this->withToken($this->token)->postJson('/api/v2/staff/roles', [
            'store_id'       => $this->store->id,
            'name'           => 'floor_manager',
            'display_name'   => 'Floor Manager',
            'description'    => 'Manages the sales floor',
            'permission_ids' => $permIds,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'floor_manager')
            ->assertJsonPath('data.is_predefined', false);

        $this->assertDatabaseHas('roles', [
            'name'     => 'floor_manager',
            'store_id' => $this->store->id,
        ]);
    }

    public function test_can_list_roles_for_store(): void
    {
        // Create a role first
        Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'test_role',
            'display_name'  => 'Test Role',
            'guard_name'    => 'staff',
            'is_predefined' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/roles');

        $response->assertOk();

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_can_show_role_with_permissions(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'test_show',
            'display_name'  => 'Test Show',
            'guard_name'    => 'staff',
            'is_predefined' => false,
        ]);
        $role->permissions()->attach(Permission::first()->id);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'test_show')
            ->assertJsonStructure(['data' => ['permissions']]);
    }

    public function test_can_update_custom_role(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'updatable',
            'display_name'  => 'Updatable',
            'guard_name'    => 'staff',
            'is_predefined' => false,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/roles/{$role->id}", [
                'display_name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.display_name', 'Updated Name');
    }

    public function test_cannot_update_predefined_role(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'predefined',
            'display_name'  => 'Predefined',
            'guard_name'    => 'staff',
            'is_predefined' => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/roles/{$role->id}", [
                'display_name' => 'Hacked',
            ]);

        $response->assertStatus(500); // InvalidArgumentException
    }

    public function test_can_delete_custom_role(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'deletable',
            'display_name'  => 'Deletable',
            'guard_name'    => 'staff',
            'is_predefined' => false,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/roles/{$role->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_predefined_role(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'undeletable',
            'display_name'  => 'Undeletable',
            'guard_name'    => 'staff',
            'is_predefined' => true,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/roles/{$role->id}");

        $response->assertStatus(500);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    // ─── Role Assignment ─────────────────────────────────────────

    public function test_can_assign_role_to_user(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'assignable',
            'display_name'  => 'Assignable',
            'guard_name'    => 'staff',
            'is_predefined' => false,
        ]);

        $cashier = User::create([
            'name'          => 'Cashier',
            'email'         => 'cashier@test.com',
            'password_hash' => bcrypt('password'),
            'store_id'      => $this->store->id,
            'role'          => 'cashier',
            'is_active'     => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role->id}/assign", [
                'user_id' => $cashier->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('model_has_roles', [
            'role_id'  => $role->id,
            'model_id' => $cashier->id,
        ]);
    }

    public function test_can_unassign_role_from_user(): void
    {
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'removable',
            'display_name'  => 'Removable',
            'guard_name'    => 'staff',
            'is_predefined' => false,
        ]);

        $cashier = User::create([
            'name'          => 'Cashier2',
            'email'         => 'cashier2@test.com',
            'password_hash' => bcrypt('password'),
            'store_id'      => $this->store->id,
            'role'          => 'cashier',
            'is_active'     => true,
        ]);

        // Assign first
        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id'    => $role->id,
            'model_id'   => $cashier->id,
            'model_type' => get_class($cashier),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/roles/{$role->id}/unassign", [
                'user_id' => $cashier->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('model_has_roles', [
            'role_id'  => $role->id,
            'model_id' => $cashier->id,
        ]);
    }

    // ─── User Permissions ────────────────────────────────────────

    public function test_can_get_effective_user_permissions(): void
    {
        // Create role with POS permissions
        $role = Role::create([
            'store_id'      => $this->store->id,
            'name'          => 'pos_user',
            'display_name'  => 'POS User',
            'guard_name'    => 'staff',
            'is_predefined' => false,
        ]);

        $posPerms = Permission::where('module', 'pos')->pluck('id');
        $role->permissions()->attach($posPerms);

        // Assign role to owner
        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id'    => $role->id,
            'model_id'   => $this->owner->id,
            'model_type' => get_class($this->owner),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/roles/user-permissions?store_id={$this->store->id}");

        $response->assertOk();

        $permissions = $response->json('data.permissions');
        $this->assertContains('pos.sell', $permissions);
        $this->assertContains('pos.open_session', $permissions);
    }

    // ─── PIN Override ────────────────────────────────────────────

    public function test_can_check_if_permission_requires_pin(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/pin-override/check?permission_code=pos.void_transaction');

        $response->assertOk()
            ->assertJsonPath('data.requires_pin', true);
    }

    public function test_non_pin_permission_returns_false(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/pin-override/check?permission_code=pos.sell');

        $response->assertOk()
            ->assertJsonPath('data.requires_pin', false);
    }

    // ─── Audit Log ───────────────────────────────────────────────

    public function test_role_creation_creates_audit_log(): void
    {
        $this->withToken($this->token)->postJson('/api/v2/staff/roles', [
            'store_id'     => $this->store->id,
            'name'         => 'audited_role',
            'display_name' => 'Audited Role',
        ]);

        $this->assertDatabaseHas('role_audit_log', [
            'store_id' => $this->store->id,
            'user_id'  => $this->owner->id,
            'action'   => 'role_created',
        ]);
    }

    // ─── Auth Guard ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_roles(): void
    {
        $response = $this->getJson('/api/v2/staff/roles');

        $response->assertUnauthorized();
    }

    // ─── Default Template Sync ───────────────────────────────────

    public function test_list_roles_syncs_default_templates(): void
    {
        // Create a default role template
        \App\Domain\StaffManagement\Models\DefaultRoleTemplate::create([
            'name'        => 'Cashier',
            'name_ar'     => 'كاشير',
            'slug'        => 'cashier',
            'description' => 'Process sales',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/roles');

        $response->assertOk();

        // The cashier template should now appear as a predefined role
        $this->assertDatabaseHas('roles', [
            'store_id'      => $this->store->id,
            'name'          => 'cashier',
            'is_predefined' => true,
        ]);
    }

    public function test_list_roles_does_not_duplicate_existing_roles(): void
    {
        \App\Domain\StaffManagement\Models\DefaultRoleTemplate::create([
            'name'        => 'Cashier',
            'name_ar'     => 'كاشير',
            'slug'        => 'cashier',
            'description' => 'Process sales',
        ]);

        // Call twice — should only create one role
        $this->withToken($this->token)->getJson('/api/v2/staff/roles');
        $this->withToken($this->token)->getJson('/api/v2/staff/roles');

        $this->assertEquals(1, \App\Domain\StaffManagement\Models\Role::where('store_id', $this->store->id)
            ->where('name', 'cashier')
            ->count());
    }

    public function test_role_audit_log_returns_paginated_entries(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'name'         => 'auditable_role',
                'display_name' => 'Auditable Role',
                'store_id'     => $this->store->id,
            ]);

        $roleId = $createResponse->json('data.id');

        $this->withToken($this->token)
            ->putJson("/api/v2/staff/roles/{$roleId}", [
                'display_name' => 'Auditable Role Updated',
                'store_id'     => $this->store->id,
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/roles/audit-log');

        $response->assertOk()
            ->assertJsonPath('data.total', fn($val) => $val >= 2)
            ->assertJsonStructure([
                'data' => [
                    'data'         => [['id', 'action', 'role_id', 'user_id', 'user_name', 'created_at']],
                    'total', 'per_page', 'current_page',
                ],
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('data.total'));
    }

    public function test_role_audit_log_filter_by_action(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'name'         => 'filter_role',
                'display_name' => 'Filter Role',
                'store_id'     => $this->store->id,
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/roles/audit-log?action=role_created');

        $response->assertOk();

        foreach ($response->json('data.data') as $entry) {
            $this->assertEquals('role_created', $entry['action']);
        }
    }

    public function test_role_audit_log_filter_by_date_range(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/staff/roles', [
                'name'         => 'date_role',
                'display_name' => 'Date Role',
                'store_id'     => $this->store->id,
            ]);

        $today = now()->toDateString();
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/roles/audit-log?date_from={$today}&date_to={$today}");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    public function test_role_audit_log_cross_store_isolation(): void
    {
        // Create separate org/store/user
        $otherOrg = \App\Domain\Core\Models\Organization::create([
            'name'          => 'Other Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);
        $otherStore = \App\Domain\Core\Models\Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
        ]);
        $otherUser = User::create([
            'name'            => 'Other User',
            'email'           => 'other@isolation.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        // Create audit log entry for other store
        $otherRole = \App\Domain\StaffManagement\Models\Role::create([
            'store_id'     => $otherStore->id,
            'name'         => 'other_role',
            'display_name' => 'Other Role',
            'guard_name'   => 'web',
        ]);

        \App\Domain\Security\Models\RoleAuditLog::create([
            'store_id' => $otherStore->id,
            'user_id'  => $otherUser->id,
            'action'   => \App\Domain\Security\Enums\RoleAuditAction::RoleCreated,
            'role_id'  => $otherRole->id,
            'details'  => [],
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/roles/audit-log');

        $response->assertOk();

        // None of the returned entries should belong to the other store
        foreach ($response->json('data') as $entry) {
            $this->assertNotEquals($otherRole->id, $entry['role_id'] ?? null);
        }
    }
}
