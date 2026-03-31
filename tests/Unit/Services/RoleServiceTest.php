<?php

namespace Tests\Unit\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoleService $roleService;
    private User $owner;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionService::class)->seedAll();

        $org = Organization::create([
            'name' => 'Test Org',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'currency' => 'SAR',
        ]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password_hash' => bcrypt('p'),
            'store_id' => $this->store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->roleService = app(RoleService::class);
    }

    // ─── Create ──────────────────────────────────────────────────

    public function test_create_role_with_permissions(): void
    {
        $permIds = Permission::where('module', 'pos')->pluck('id')->toArray();

        $role = $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'test_role',
            'display_name' => 'Test Role',
            'permission_ids' => $permIds,
        ], $this->owner);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('test_role', $role->name);
        $this->assertFalse($role->is_predefined);
        $this->assertCount(count($permIds), $role->permissions);
    }

    public function test_create_role_without_permissions(): void
    {
        $role = $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'empty_role',
            'display_name' => 'Empty',
        ], $this->owner);

        $this->assertEquals(0, $role->permissions->count());
    }

    public function test_create_role_generates_audit_log(): void
    {
        $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'audited',
            'display_name' => 'Audited',
        ], $this->owner);

        $this->assertDatabaseHas('role_audit_log', [
            'store_id' => $this->store->id,
            'user_id' => $this->owner->id,
            'action' => 'role_created',
        ]);
    }

    // ─── Update ──────────────────────────────────────────────────

    public function test_update_custom_role(): void
    {
        $role = $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'updatable',
            'display_name' => 'Updatable',
        ], $this->owner);

        $updated = $this->roleService->update($role, [
            'display_name' => 'Updated Name',
        ], $this->owner);

        $this->assertEquals('Updated Name', $updated->display_name);
    }

    public function test_update_predefined_role_throws(): void
    {
        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'predefined',
            'display_name' => 'Predefined',
            'guard_name' => 'staff',
            'is_predefined' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->roleService->update($role, [
            'display_name' => 'Changed',
        ], $this->owner);
    }

    public function test_update_syncs_permissions(): void
    {
        $posPerms = Permission::where('module', 'pos')->pluck('id')->toArray();
        $invPerms = Permission::where('module', 'inventory')->pluck('id')->toArray();

        $role = $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'syncable',
            'display_name' => 'Syncable',
            'permission_ids' => $posPerms,
        ], $this->owner);

        $this->assertCount(count($posPerms), $role->permissions);

        $updated = $this->roleService->update($role, [
            'permission_ids' => $invPerms,
        ], $this->owner);

        $this->assertCount(count($invPerms), $updated->permissions);
    }

    // ─── Delete ──────────────────────────────────────────────────

    public function test_delete_custom_role(): void
    {
        $role = $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'deletable',
            'display_name' => 'Deletable',
        ], $this->owner);

        $this->roleService->delete($role, $this->owner);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_delete_predefined_role_throws(): void
    {
        $role = Role::create([
            'store_id' => $this->store->id,
            'name' => 'undeletable',
            'display_name' => 'Undeletable',
            'guard_name' => 'staff',
            'is_predefined' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->roleService->delete($role, $this->owner);
    }

    // ─── List ────────────────────────────────────────────────────

    public function test_list_returns_roles_for_correct_store(): void
    {
        $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'store1_role',
            'display_name' => 'S1',
        ], $this->owner);

        $roles = $this->roleService->listForStore($this->store->id);
        $this->assertGreaterThanOrEqual(1, $roles->count());
    }

    public function test_list_sorts_predefined_first(): void
    {
        Role::create([
            'store_id' => $this->store->id,
            'name' => 'z_custom',
            'display_name' => 'Z',
            'guard_name' => 'staff',
            'is_predefined' => false,
        ]);

        Role::create([
            'store_id' => $this->store->id,
            'name' => 'a_predefined',
            'display_name' => 'A',
            'guard_name' => 'staff',
            'is_predefined' => true,
        ]);

        $roles = $this->roleService->listForStore($this->store->id);
        $this->assertTrue($roles->first()->is_predefined);
    }

    // ─── Assign / Unassign ───────────────────────────────────────

    public function test_assign_role_to_user(): void
    {
        $role = $this->roleService->create([
            'store_id' => $this->store->id,
            'name' => 'assignable',
            'display_name' => 'Assignable',
        ], $this->owner);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password_hash' => bcrypt('p'),
            'store_id' => $this->store->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->roleService->assignToUser($role, $cashier, $this->owner);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_id' => $cashier->id,
        ]);
    }

    // ─── Permission Service ──────────────────────────────────────

    public function test_seed_all_creates_permissions(): void
    {
        $count = Permission::count();
        $this->assertGreaterThan(50, $count);
    }

    public function test_permissions_have_modules(): void
    {
        $modules = Permission::distinct()->pluck('module')->toArray();
        $this->assertContains('pos', $modules);
        $this->assertContains('orders', $modules);
        $this->assertContains('inventory', $modules);
    }

    public function test_some_permissions_require_pin(): void
    {
        $pinProtected = Permission::where('requires_pin', true)->count();
        $this->assertGreaterThan(0, $pinProtected);
    }

    // ─── syncDefaultTemplates ────────────────────────────────────

    public function test_sync_creates_predefined_roles_from_templates(): void
    {
        \App\Domain\StaffManagement\Models\DefaultRoleTemplate::create([
            'name'        => 'Cashier',
            'name_ar'     => 'كاشير',
            'slug'        => 'cashier',
            'description' => 'Process sales',
        ]);

        \App\Domain\StaffManagement\Models\DefaultRoleTemplate::create([
            'name'        => 'Branch Manager',
            'name_ar'     => 'مدير الفرع',
            'slug'        => 'branch_manager',
            'description' => 'Manage store',
        ]);

        $this->roleService->syncDefaultTemplates($this->store->id);

        $this->assertDatabaseHas('roles', ['store_id' => $this->store->id, 'name' => 'cashier', 'is_predefined' => true]);
        $this->assertDatabaseHas('roles', ['store_id' => $this->store->id, 'name' => 'branch_manager', 'is_predefined' => true]);
    }

    public function test_sync_attaches_permissions_by_name(): void
    {
        $perm = Permission::where('module', 'pos')->first();

        $template = \App\Domain\StaffManagement\Models\DefaultRoleTemplate::create([
            'name'        => 'Cashier',
            'name_ar'     => 'كاشير',
            'slug'        => 'cashier',
            'description' => 'Process sales',
        ]);

        \App\Domain\ProviderRegistration\Models\ProviderPermission::create([
            'name'        => $perm->name,
            'group'       => 'pos',
            'description' => $perm->name,
            'is_active'   => true,
        ]);

        // Attach via pivot
        \DB::table('default_role_template_permissions')->insert([
            'id'                       => \Illuminate\Support\Str::uuid(),
            'default_role_template_id' => $template->id,
            'provider_permission_id'   => \App\Domain\ProviderRegistration\Models\ProviderPermission::where('name', $perm->name)->value('id'),
        ]);

        $this->roleService->syncDefaultTemplates($this->store->id);

        $role = Role::where('store_id', $this->store->id)->where('name', 'cashier')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $role->permissions->count());
    }

    public function test_sync_does_not_duplicate_existing_predefined_roles(): void
    {
        \App\Domain\StaffManagement\Models\DefaultRoleTemplate::create([
            'name'        => 'Cashier',
            'name_ar'     => 'كاشير',
            'slug'        => 'cashier',
            'description' => 'Process sales',
        ]);

        $this->roleService->syncDefaultTemplates($this->store->id);
        $this->roleService->syncDefaultTemplates($this->store->id); // second call

        $this->assertEquals(1, Role::where('store_id', $this->store->id)
            ->where('name', 'cashier')
            ->count());
    }
}
