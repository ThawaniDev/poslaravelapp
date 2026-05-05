<?php

namespace Tests\Unit\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\AdminPanel\Services\PlatformRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit tests for PlatformRoleService.
 *
 * Covers: listRoles, getRole, createRole, updateRole, deleteRole,
 * listPermissions, listPermissionsGrouped, listAdminUsers, getAdminUser,
 * createAdminUser, updateAdminUser, deactivateAdminUser, activateAdminUser,
 * getAdminUserProfile, listActivityLogs, logActivity.
 */
class PlatformRoleServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlatformRoleService $service;
    private AdminUser $admin;
    private AdminRole $superAdminRole;
    private AdminRole $viewerRole;
    private AdminRole $customRole;
    private AdminPermission $storesView;
    private AdminPermission $storesEdit;
    private AdminPermission $billingView;
    private AdminPermission $billingEdit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PlatformRoleService();

        $this->admin = AdminUser::forceCreate([
            'name'          => 'Service Test Admin',
            'email'         => 'svc@thawani.test',
            'password_hash' => bcrypt('secure_pass_123'),
            'is_active'     => true,
        ]);

        $this->superAdminRole = AdminRole::create([
            'name'      => 'Super Admin',
            'slug'      => 'super_admin',
            'is_system' => true,
        ]);

        $this->viewerRole = AdminRole::create([
            'name'      => 'Viewer',
            'slug'      => 'viewer',
            'is_system' => true,
        ]);

        $this->customRole = AdminRole::create([
            'name'      => 'Support Agent',
            'slug'      => 'support_agent',
            'is_system' => false,
        ]);

        $this->storesView = AdminPermission::create([
            'name'        => 'stores.view',
            'group'       => 'stores',
            'description' => 'View stores',
        ]);

        $this->storesEdit = AdminPermission::create([
            'name'        => 'stores.edit',
            'group'       => 'stores',
            'description' => 'Edit stores',
        ]);

        $this->billingView = AdminPermission::create([
            'name'        => 'billing.view',
            'group'       => 'billing',
            'description' => 'View billing',
        ]);

        $this->billingEdit = AdminPermission::create([
            'name'        => 'billing.edit',
            'group'       => 'billing',
            'description' => 'Edit billing',
        ]);

        AdminRolePermission::create([
            'admin_role_id'       => $this->superAdminRole->id,
            'admin_permission_id' => $this->storesView->id,
        ]);
        AdminRolePermission::create([
            'admin_role_id'       => $this->superAdminRole->id,
            'admin_permission_id' => $this->billingView->id,
        ]);

        AdminRolePermission::create([
            'admin_role_id'       => $this->viewerRole->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $this->admin->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // listRoles
    // ══════════════════════════════════════════════════════════

    public function test_list_roles_returns_all_roles(): void
    {
        $roles = $this->service->listRoles();
        $this->assertCount(3, $roles); // super_admin, viewer, support_agent
    }

    public function test_list_roles_includes_user_count(): void
    {
        $roles = $this->service->listRoles();
        $super = $roles->firstWhere('slug', 'super_admin');
        $this->assertEquals(1, $super->admin_user_roles_count);
    }

    public function test_list_roles_includes_permission_count(): void
    {
        $roles = $this->service->listRoles();
        $super = $roles->firstWhere('slug', 'super_admin');
        $this->assertEquals(2, $super->admin_role_permissions_count);
    }

    public function test_list_roles_orders_system_roles_first(): void
    {
        $roles = $this->service->listRoles();
        // First role should be system=true
        $this->assertTrue($roles->first()->is_system);
        // Last role (customRole, support_agent) should be system=false
        $lastRole = $roles->last();
        $this->assertFalse($lastRole->is_system);
    }

    public function test_list_roles_orders_alphabetically_within_groups(): void
    {
        // Add another non-system role that sorts before support_agent alphabetically
        AdminRole::create([
            'name'      => 'Alpha Custom',
            'slug'      => 'alpha_custom',
            'is_system' => false,
        ]);

        $roles = $this->service->listRoles();
        $nonSystem = $roles->where('is_system', false)->values();

        $this->assertEquals('Alpha Custom', $nonSystem->get(0)->name);
        $this->assertEquals('Support Agent', $nonSystem->get(1)->name);
    }

    public function test_list_roles_returns_empty_collection_when_no_roles(): void
    {
        AdminUserRole::query()->delete();
        AdminRolePermission::query()->delete();
        AdminRole::query()->delete();

        $roles = $this->service->listRoles();
        $this->assertCount(0, $roles);
    }

    // ══════════════════════════════════════════════════════════
    // getRole
    // ══════════════════════════════════════════════════════════

    public function test_get_role_returns_role_with_permissions_loaded(): void
    {
        $role = $this->service->getRole($this->superAdminRole->id);

        $this->assertNotNull($role);
        $this->assertEquals('super_admin', $role->slug);
        $this->assertTrue($role->relationLoaded('adminRolePermissions'));
        $this->assertCount(2, $role->adminRolePermissions);
    }

    public function test_get_role_includes_permission_count(): void
    {
        $role = $this->service->getRole($this->superAdminRole->id);
        $this->assertEquals(2, $role->admin_role_permissions_count);
    }

    public function test_get_role_includes_user_count(): void
    {
        $role = $this->service->getRole($this->superAdminRole->id);
        $this->assertEquals(1, $role->admin_user_roles_count);
    }

    public function test_get_role_returns_null_for_missing_id(): void
    {
        $role = $this->service->getRole('00000000-0000-0000-0000-000000000000');
        $this->assertNull($role);
    }

    public function test_get_role_returns_null_for_invalid_uuid(): void
    {
        $role = $this->service->getRole('not-a-valid-uuid');
        $this->assertNull($role);
    }

    // ══════════════════════════════════════════════════════════
    // createRole
    // ══════════════════════════════════════════════════════════

    public function test_create_role_minimal_data(): void
    {
        $role = $this->service->createRole(['name' => 'My New Role'], $this->admin->id);

        $this->assertDatabaseHas('admin_roles', [
            'name'      => 'My New Role',
            'is_system' => false,
        ]);
        $this->assertNotNull($role->id);
    }

    public function test_create_role_generates_slug_from_name(): void
    {
        $role = $this->service->createRole(['name' => 'Store Manager'], $this->admin->id);
        $this->assertEquals('store_manager', $role->slug);
    }

    public function test_create_role_uses_provided_custom_slug(): void
    {
        $role = $this->service->createRole([
            'name' => 'Custom Slug Role',
            'slug' => 'my_custom_slug',
        ], $this->admin->id);
        $this->assertEquals('my_custom_slug', $role->slug);
    }

    public function test_create_role_with_description(): void
    {
        $role = $this->service->createRole([
            'name'        => 'Described Role',
            'description' => 'Role with a description',
        ], $this->admin->id);
        $this->assertEquals('Role with a description', $role->description);
    }

    public function test_create_role_with_permissions(): void
    {
        $role = $this->service->createRole([
            'name'           => 'Role With Perms',
            'permission_ids' => [$this->storesView->id, $this->storesEdit->id],
        ], $this->admin->id);

        $this->assertEquals(2, $role->admin_role_permissions_count);
        $this->assertDatabaseHas('admin_role_permissions', [
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);
        $this->assertDatabaseHas('admin_role_permissions', [
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesEdit->id,
        ]);
    }

    public function test_create_role_without_permissions_has_zero_count(): void
    {
        $role = $this->service->createRole(['name' => 'No Perms Role'], $this->admin->id);
        $this->assertEquals(0, $role->admin_role_permissions_count);
    }

    public function test_create_role_logs_activity_with_correct_fields(): void
    {
        $role = $this->service->createRole(['name' => 'Logged Role'], $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'role.create',
            'entity_type'   => 'admin_role',
            'entity_id'     => $role->id,
        ]);
    }

    public function test_create_role_is_never_system(): void
    {
        $role = $this->service->createRole(['name' => 'Custom'], $this->admin->id);
        $this->assertFalse($role->is_system);
    }

    public function test_create_role_returns_role_with_relations_loaded(): void
    {
        $role = $this->service->createRole([
            'name'           => 'With Relations',
            'permission_ids' => [$this->storesView->id],
        ], $this->admin->id);

        $this->assertTrue($role->relationLoaded('adminRolePermissions'));
    }

    // ══════════════════════════════════════════════════════════
    // updateRole
    // ══════════════════════════════════════════════════════════

    public function test_update_role_name_for_custom_role(): void
    {
        $updated = $this->service->updateRole($this->customRole, ['name' => 'Updated Agent'], $this->admin->id);
        $this->assertEquals('Updated Agent', $updated->name);
        $this->assertDatabaseHas('admin_roles', ['id' => $this->customRole->id, 'name' => 'Updated Agent']);
    }

    public function test_update_role_cannot_rename_system_role(): void
    {
        $updated = $this->service->updateRole($this->superAdminRole, ['name' => 'Hacked Name'], $this->admin->id);
        // System role name is protected
        $this->assertEquals('Super Admin', $updated->name);
        $this->assertDatabaseHas('admin_roles', ['id' => $this->superAdminRole->id, 'name' => 'Super Admin']);
    }

    public function test_update_role_can_update_description_for_system_role(): void
    {
        $updated = $this->service->updateRole($this->superAdminRole, [
            'description' => 'Updated description',
        ], $this->admin->id);
        $this->assertEquals('Updated description', $updated->description);
    }

    public function test_update_role_replaces_permissions_atomically(): void
    {
        $role = AdminRole::create(['name' => 'Perm Test', 'slug' => 'perm_test', 'is_system' => false]);
        AdminRolePermission::create([
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        $updated = $this->service->updateRole($role, [
            'permission_ids' => [$this->storesEdit->id, $this->billingEdit->id],
        ], $this->admin->id);

        $this->assertEquals(2, $updated->admin_role_permissions_count);
        $this->assertDatabaseHas('admin_role_permissions', [
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesEdit->id,
        ]);
        $this->assertDatabaseMissing('admin_role_permissions', [
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);
    }

    public function test_update_role_clears_all_permissions_with_empty_array(): void
    {
        $role = AdminRole::create(['name' => 'Clear Test', 'slug' => 'clear_test', 'is_system' => false]);
        AdminRolePermission::create([
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        $updated = $this->service->updateRole($role, ['permission_ids' => []], $this->admin->id);
        $this->assertEquals(0, $updated->admin_role_permissions_count);
        $this->assertDatabaseMissing('admin_role_permissions', ['admin_role_id' => $role->id]);
    }

    public function test_update_role_without_permission_ids_keeps_existing_permissions(): void
    {
        $role = AdminRole::create(['name' => 'Keep Perms', 'slug' => 'keep_perms', 'is_system' => false]);
        AdminRolePermission::create([
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        // Update name only, no permission_ids key
        $updated = $this->service->updateRole($role, ['name' => 'Keep Perms Updated'], $this->admin->id);
        $this->assertEquals(1, $updated->admin_role_permissions_count);
    }

    public function test_update_role_logs_activity(): void
    {
        $this->service->updateRole($this->customRole, ['name' => 'Updated'], $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'role.update',
            'entity_type'   => 'admin_role',
            'entity_id'     => $this->customRole->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // deleteRole
    // ══════════════════════════════════════════════════════════

    public function test_delete_custom_role_returns_true_and_removes_it(): void
    {
        $role   = AdminRole::create(['name' => 'To Delete', 'slug' => 'to_del', 'is_system' => false]);
        $result = $this->service->deleteRole($role, $this->admin->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('admin_roles', ['id' => $role->id]);
    }

    public function test_delete_system_role_returns_false(): void
    {
        $result = $this->service->deleteRole($this->superAdminRole, $this->admin->id);

        $this->assertFalse($result);
        $this->assertDatabaseHas('admin_roles', ['id' => $this->superAdminRole->id]);
    }

    public function test_delete_role_with_assigned_users_returns_false(): void
    {
        $role = AdminRole::create(['name' => 'Has Users', 'slug' => 'has_users', 'is_system' => false]);
        AdminUserRole::create([
            'admin_user_id' => $this->admin->id,
            'admin_role_id' => $role->id,
            'assigned_at'   => now(),
        ]);

        $result = $this->service->deleteRole($role, $this->admin->id);

        $this->assertFalse($result);
        $this->assertDatabaseHas('admin_roles', ['id' => $role->id]);
    }

    public function test_delete_role_cascades_permissions(): void
    {
        $role = AdminRole::create(['name' => 'With Perms', 'slug' => 'with_perms', 'is_system' => false]);
        AdminRolePermission::create([
            'admin_role_id'       => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        $this->service->deleteRole($role, $this->admin->id);

        $this->assertDatabaseMissing('admin_role_permissions', ['admin_role_id' => $role->id]);
    }

    public function test_delete_role_logs_activity_before_deleting(): void
    {
        $role = AdminRole::create(['name' => 'Log Delete', 'slug' => 'log_delete', 'is_system' => false]);
        $this->service->deleteRole($role, $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'role.delete',
            'entity_type'   => 'admin_role',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // listPermissions & listPermissionsGrouped
    // ══════════════════════════════════════════════════════════

    public function test_list_permissions_returns_all_permissions(): void
    {
        $perms = $this->service->listPermissions();
        $this->assertCount(4, $perms); // storesView, storesEdit, billingView, billingEdit
    }

    public function test_list_permissions_ordered_by_group_then_name(): void
    {
        $perms  = $this->service->listPermissions();
        $names  = $perms->pluck('name')->toArray();

        // billing group comes before stores alphabetically
        $billingIdx = array_search('billing.edit', $names);
        $storesIdx  = array_search('stores.view', $names);
        $this->assertLessThan($storesIdx, $billingIdx);
    }

    public function test_list_permissions_grouped_returns_correct_groups(): void
    {
        $grouped = $this->service->listPermissionsGrouped();

        $this->assertArrayHasKey('stores', $grouped);
        $this->assertArrayHasKey('billing', $grouped);
    }

    public function test_list_permissions_grouped_stores_has_two_entries(): void
    {
        $grouped = $this->service->listPermissionsGrouped();
        $this->assertCount(2, $grouped['stores']);
    }

    public function test_list_permissions_grouped_billing_has_two_entries(): void
    {
        $grouped = $this->service->listPermissionsGrouped();
        $this->assertCount(2, $grouped['billing']);
    }

    public function test_list_permissions_grouped_each_entry_has_id_name_description(): void
    {
        $grouped = $this->service->listPermissionsGrouped();
        $entry   = $grouped['stores'][0];

        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('description', $entry);
    }

    public function test_list_permissions_grouped_does_not_include_group_key_inside_entry(): void
    {
        $grouped = $this->service->listPermissionsGrouped();
        $entry   = $grouped['stores'][0];

        $this->assertArrayNotHasKey('group', $entry);
    }

    // ══════════════════════════════════════════════════════════
    // listAdminUsers
    // ══════════════════════════════════════════════════════════

    public function test_list_admin_users_returns_paginated_result(): void
    {
        $paginator = $this->service->listAdminUsers([], 10);
        $this->assertEquals(1, $paginator->total());
    }

    public function test_list_admin_users_search_by_name(): void
    {
        AdminUser::forceCreate([
            'name'          => 'Sara Support',
            'email'         => 'sara@thawani.test',
            'password_hash' => bcrypt('pass_secure'),
            'is_active'     => true,
        ]);

        $result = $this->service->listAdminUsers(['search' => 'Sara'], 10);
        $this->assertEquals(1, $result->total());
        $this->assertEquals('Sara Support', $result->items()[0]->name);
    }

    public function test_list_admin_users_search_by_email(): void
    {
        AdminUser::forceCreate([
            'name'          => 'Ahmed Search',
            'email'         => 'uniqueemail@thawani.test',
            'password_hash' => bcrypt('pass_secure'),
            'is_active'     => true,
        ]);

        $result = $this->service->listAdminUsers(['search' => 'uniqueemail'], 10);
        $this->assertEquals(1, $result->total());
    }

    public function test_list_admin_users_filter_active_users(): void
    {
        AdminUser::forceCreate([
            'name'          => 'Inactive User',
            'email'         => 'inactive@thawani.test',
            'password_hash' => bcrypt('pass_secure'),
            'is_active'     => false,
        ]);

        $active   = $this->service->listAdminUsers(['is_active' => true], 10);
        $inactive = $this->service->listAdminUsers(['is_active' => false], 10);

        $this->assertEquals(1, $active->total());
        $this->assertEquals(1, $inactive->total());
    }

    public function test_list_admin_users_filter_by_role_id(): void
    {
        AdminUser::forceCreate([
            'name'          => 'No Role User',
            'email'         => 'norole@thawani.test',
            'password_hash' => bcrypt('pass_secure'),
            'is_active'     => true,
        ]);

        $result = $this->service->listAdminUsers(['role_id' => $this->superAdminRole->id], 10);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($this->admin->email, $result->items()[0]->email);
    }

    public function test_list_admin_users_pagination_respects_per_page(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            AdminUser::forceCreate([
                'name'          => "User {$i}",
                'email'         => "user{$i}@thawani.test",
                'password_hash' => bcrypt('pass'),
                'is_active'     => true,
            ]);
        }

        $result = $this->service->listAdminUsers([], 3);
        $this->assertCount(3, $result->items());
        $this->assertEquals(6, $result->total()); // 1 original + 5 new
    }

    // ══════════════════════════════════════════════════════════
    // getAdminUser
    // ══════════════════════════════════════════════════════════

    public function test_get_admin_user_returns_user_with_roles_loaded(): void
    {
        $user = $this->service->getAdminUser($this->admin->id);

        $this->assertNotNull($user);
        $this->assertTrue($user->relationLoaded('adminUserRoles'));
    }

    public function test_get_admin_user_returns_null_for_missing_id(): void
    {
        $user = $this->service->getAdminUser('00000000-0000-0000-0000-000000000000');
        $this->assertNull($user);
    }

    // ══════════════════════════════════════════════════════════
    // createAdminUser
    // ══════════════════════════════════════════════════════════

    public function test_create_admin_user_persists_to_database(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'New Admin',
            'email'    => 'new@thawani.test',
            'password' => 'secure_password_123',
        ], $this->admin->id);

        $this->assertDatabaseHas('admin_users', ['email' => 'new@thawani.test']);
        $this->assertEquals('New Admin', $user->name);
    }

    public function test_create_admin_user_with_role_ids(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'Roled Admin',
            'email'    => 'roled@thawani.test',
            'password' => 'secure_password_456',
            'role_ids' => [$this->viewerRole->id],
        ], $this->admin->id);

        $this->assertDatabaseHas('admin_user_roles', [
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
        ]);
    }

    public function test_create_admin_user_with_multiple_roles(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'Multi Role Admin',
            'email'    => 'multirole@thawani.test',
            'password' => 'secure_password_789',
            'role_ids' => [$this->viewerRole->id, $this->customRole->id],
        ], $this->admin->id);

        $this->assertCount(2, $user->adminUserRoles);
    }

    public function test_create_admin_user_hashes_password(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'Hash Test',
            'email'    => 'hash@thawani.test',
            'password' => 'plain_text_pass',
        ], $this->admin->id);

        $fresh = AdminUser::find($user->id);
        $this->assertNotEquals('plain_text_pass', $fresh->password_hash);
        $this->assertTrue(password_verify('plain_text_pass', $fresh->password_hash));
    }

    public function test_create_admin_user_logs_activity(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'Logged Admin',
            'email'    => 'logged@thawani.test',
            'password' => 'secure_password_log',
        ], $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'admin_user.create',
            'entity_type'   => 'admin_user',
            'entity_id'     => $user->id,
        ]);
    }

    public function test_create_admin_user_returns_user_with_roles_loaded(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'Relations Test',
            'email'    => 'rel@thawani.test',
            'password' => 'secure_pass_rel',
        ], $this->admin->id);

        $this->assertTrue($user->relationLoaded('adminUserRoles'));
    }

    public function test_create_admin_user_defaults_is_active_to_true(): void
    {
        $user = $this->service->createAdminUser([
            'name'     => 'Active Default',
            'email'    => 'activedef@thawani.test',
            'password' => 'secure_pass_act',
        ], $this->admin->id);

        $this->assertTrue($user->is_active);
    }

    public function test_create_admin_user_can_be_created_inactive(): void
    {
        $user = $this->service->createAdminUser([
            'name'      => 'Inactive From Start',
            'email'     => 'inactstart@thawani.test',
            'password'  => 'secure_pass_inact',
            'is_active' => false,
        ], $this->admin->id);

        $this->assertFalse($user->is_active);
    }

    // ══════════════════════════════════════════════════════════
    // updateAdminUser
    // ══════════════════════════════════════════════════════════

    public function test_update_admin_user_name(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Old Name',
            'email'         => 'oldname@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $updated = $this->service->updateAdminUser($user, ['name' => 'New Name'], $this->admin->id);
        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('admin_users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_admin_user_phone(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Phone User',
            'email'         => 'phone@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $updated = $this->service->updateAdminUser($user, ['phone' => '+96812345678'], $this->admin->id);
        $this->assertEquals('+96812345678', $updated->phone);
    }

    public function test_update_admin_user_deactivate(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Active User',
            'email'         => 'activeu@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $updated = $this->service->updateAdminUser($user, ['is_active' => false], $this->admin->id);
        $this->assertFalse($updated->is_active);
    }

    public function test_update_admin_user_replaces_roles(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Role Replace Test',
            'email'         => 'rolereplace@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        $this->service->updateAdminUser($user, ['role_ids' => [$this->customRole->id]], $this->admin->id);

        $this->assertDatabaseHas('admin_user_roles', [
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->customRole->id,
        ]);
        $this->assertDatabaseMissing('admin_user_roles', [
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
        ]);
    }

    public function test_update_admin_user_without_role_ids_keeps_existing(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Keep Roles Test',
            'email'         => 'keeproles@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        $this->service->updateAdminUser($user, ['name' => 'Updated Name'], $this->admin->id);

        $this->assertDatabaseHas('admin_user_roles', [
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
        ]);
    }

    public function test_update_admin_user_logs_activity(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Log Update',
            'email'         => 'logupdate@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $this->service->updateAdminUser($user, ['name' => 'Updated'], $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'admin_user.update',
            'entity_type'   => 'admin_user',
            'entity_id'     => $user->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // deactivateAdminUser / activateAdminUser
    // ══════════════════════════════════════════════════════════

    public function test_deactivate_admin_user_sets_is_active_false(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Active User',
            'email'         => 'deacttest@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $result = $this->service->deactivateAdminUser($user, $this->admin->id);

        $this->assertFalse($result->is_active);
        $this->assertDatabaseHas('admin_users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_deactivate_admin_user_logs_activity(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Deact Log',
            'email'         => 'deactlog@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $this->service->deactivateAdminUser($user, $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'admin_user.deactivate',
            'entity_type' => 'admin_user',
            'entity_id'   => $user->id,
        ]);
    }

    public function test_activate_admin_user_sets_is_active_true(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Inactive User',
            'email'         => 'acttest@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => false,
        ]);

        $result = $this->service->activateAdminUser($user, $this->admin->id);

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('admin_users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_activate_admin_user_logs_activity(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Act Log',
            'email'         => 'actlog@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => false,
        ]);

        $this->service->activateAdminUser($user, $this->admin->id);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action'      => 'admin_user.activate',
            'entity_type' => 'admin_user',
            'entity_id'   => $user->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // getAdminUserProfile
    // ══════════════════════════════════════════════════════════

    public function test_get_admin_user_profile_returns_required_keys(): void
    {
        $profile = $this->service->getAdminUserProfile($this->admin);

        $this->assertArrayHasKey('id', $profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertArrayHasKey('email', $profile);
        $this->assertArrayHasKey('roles', $profile);
        $this->assertArrayHasKey('permissions', $profile);
        $this->assertArrayHasKey('all_permissions', $profile);
    }

    public function test_get_admin_user_profile_permissions_are_correct(): void
    {
        Cache::forget("admin_user:{$this->admin->id}:permissions");
        Cache::forget("admin_user:{$this->admin->id}:is_super_admin");

        $profile = $this->service->getAdminUserProfile($this->admin);

        $this->assertContains('stores.view', $profile['permissions']);
        $this->assertContains('billing.view', $profile['permissions']);
    }

    public function test_get_admin_user_profile_permissions_equals_all_permissions(): void
    {
        $profile = $this->service->getAdminUserProfile($this->admin);
        $this->assertEquals($profile['permissions'], $profile['all_permissions']);
    }

    public function test_get_admin_user_profile_id_matches_admin(): void
    {
        $profile = $this->service->getAdminUserProfile($this->admin);
        $this->assertEquals($this->admin->id, $profile['id']);
    }

    public function test_get_admin_user_profile_roles_include_assigned_role(): void
    {
        $profile = $this->service->getAdminUserProfile($this->admin);

        $roleSlugs = array_column($profile['roles'], 'slug');
        $this->assertContains('super_admin', $roleSlugs);
    }

    public function test_get_admin_user_profile_permissions_are_unique(): void
    {
        // Give admin a second role that has storesView (already in superAdminRole)
        AdminRolePermission::create([
            'admin_role_id'       => $this->viewerRole->id,
            'admin_permission_id' => $this->billingView->id,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->admin->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        Cache::forget("admin_user:{$this->admin->id}:permissions");
        $freshAdmin = AdminUser::find($this->admin->id);
        $profile    = $this->service->getAdminUserProfile($freshAdmin);

        $this->assertEquals(
            count($profile['permissions']),
            count(array_unique($profile['permissions']))
        );
    }

    public function test_get_admin_user_profile_for_user_with_no_roles(): void
    {
        $noRoleUser = AdminUser::forceCreate([
            'name'          => 'No Role',
            'email'         => 'norole@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $profile = $this->service->getAdminUserProfile($noRoleUser);

        $this->assertEmpty($profile['roles']);
        $this->assertEmpty($profile['permissions']);
    }

    // ══════════════════════════════════════════════════════════
    // listActivityLogs
    // ══════════════════════════════════════════════════════════

    public function test_list_activity_logs_returns_paginated_result(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->logActivity(
                $this->admin->id,
                "test.action.{$i}",
                'admin_role',
                null,
                null,
                '127.0.0.1'
            );
        }

        $result = $this->service->listActivityLogs([], 3);
        $this->assertCount(3, $result->items());
        $this->assertEquals(5, $result->total());
    }

    public function test_list_activity_logs_filters_by_action(): void
    {
        $this->service->logActivity($this->admin->id, 'role.create', 'admin_role');
        $this->service->logActivity($this->admin->id, 'store.suspend', 'store');

        $result = $this->service->listActivityLogs(['action' => 'role.create'], 10);
        $this->assertCount(1, $result->items());
        $this->assertEquals('role.create', $result->items()[0]->action);
    }

    public function test_list_activity_logs_filters_by_entity_type(): void
    {
        $this->service->logActivity($this->admin->id, 'role.create', 'admin_role');
        $this->service->logActivity($this->admin->id, 'user.login', 'admin_user');

        $result = $this->service->listActivityLogs(['entity_type' => 'admin_role'], 10);
        $this->assertCount(1, $result->items());
    }

    public function test_list_activity_logs_filters_by_entity_id(): void
    {
        $targetId = $this->superAdminRole->id;
        $this->service->logActivity($this->admin->id, 'role.create', 'admin_role', $targetId);
        $this->service->logActivity($this->admin->id, 'role.create', 'admin_role', $this->viewerRole->id);

        $result = $this->service->listActivityLogs(['entity_id' => $targetId], 10);
        $this->assertCount(1, $result->items());
        $this->assertEquals($targetId, $result->items()[0]->entity_id);
    }

    public function test_list_activity_logs_filters_by_admin_user_id(): void
    {
        $otherAdmin = AdminUser::forceCreate([
            'name'          => 'Other Admin',
            'email'         => 'other@thawani.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);
        $this->service->logActivity($this->admin->id, 'action.a', null);
        $this->service->logActivity($otherAdmin->id, 'action.b', null);

        $result = $this->service->listActivityLogs(['admin_user_id' => $this->admin->id], 10);
        $this->assertCount(1, $result->items());
    }

    public function test_list_activity_logs_filters_by_date_from(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'old.action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(10),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'new.action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $result = $this->service->listActivityLogs([
            'date_from' => now()->subDays(1)->toDateString(),
        ], 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('new.action', $result->items()[0]->action);
    }

    public function test_list_activity_logs_filters_by_date_to(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'old.action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(10),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'new.action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $result = $this->service->listActivityLogs([
            'date_to' => now()->subDays(5)->toDateString(),
        ], 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('old.action', $result->items()[0]->action);
    }

    public function test_list_activity_logs_filters_by_date_range(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'very.old',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(30),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'in.range',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(5),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'recent',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $result = $this->service->listActivityLogs([
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to'   => now()->subDays(3)->toDateString(),
        ], 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('in.range', $result->items()[0]->action);
    }

    public function test_list_activity_logs_ordered_by_newest_first(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'first.action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subMinutes(10),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action'        => 'latest.action',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $result = $this->service->listActivityLogs([], 10);
        $this->assertEquals('latest.action', $result->items()[0]->action);
    }

    // ══════════════════════════════════════════════════════════
    // logActivity
    // ══════════════════════════════════════════════════════════

    public function test_log_activity_creates_record(): void
    {
        $log = $this->service->logActivity(
            $this->admin->id,
            'test.action',
            'admin_role',
            $this->superAdminRole->id,
            ['key' => 'value'],
            '192.168.1.1'
        );

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action'        => 'test.action',
            'entity_type'   => 'admin_role',
            'entity_id'     => $this->superAdminRole->id,
            'ip_address'    => '192.168.1.1',
        ]);
        $this->assertNotNull($log->id);
    }

    public function test_log_activity_defaults_ip_to_localhost(): void
    {
        $log = $this->service->logActivity($this->admin->id, 'some.action', null);

        $this->assertEquals('127.0.0.1', $log->ip_address);
    }

    public function test_log_activity_stores_details_as_json(): void
    {
        $details = ['name' => 'Test Role', 'permission_count' => 5];
        $this->service->logActivity($this->admin->id, 'role.create', 'admin_role', null, $details);

        $log = AdminActivityLog::where('action', 'role.create')->first();
        $this->assertNotNull($log);

        $decoded = json_decode($log->details, true);
        $this->assertEquals('Test Role', $decoded['name']);
        $this->assertEquals(5, $decoded['permission_count']);
    }

    public function test_log_activity_with_null_entity_info(): void
    {
        $log = $this->service->logActivity($this->admin->id, 'login', null, null);

        $this->assertNull($log->entity_type);
        $this->assertNull($log->entity_id);
    }
}
