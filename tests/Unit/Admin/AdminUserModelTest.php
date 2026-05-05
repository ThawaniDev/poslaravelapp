<?php

namespace Tests\Unit\Admin;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit tests for the AdminUser model.
 *
 * Covers: isSuperAdmin, hasPermission, hasPermissionTo, hasAnyPermission,
 * canAccessPanel, attribute visibility, getAuthPassword, relationships,
 * cache invalidation, and multi-role permission union.
 */
class AdminUserModelTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $user;
    private AdminRole $superAdminRole;
    private AdminRole $viewerRole;
    private AdminRole $customRole;
    private AdminPermission $storesView;
    private AdminPermission $storesEdit;
    private AdminPermission $billingEdit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AdminUser::forceCreate([
            'name'          => 'Test User',
            'email'         => 'test@thawani.test',
            'password_hash' => bcrypt('test_password_secure'),
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
            'description' => 'View stores list and details',
        ]);

        $this->storesEdit = AdminPermission::create([
            'name'        => 'stores.edit',
            'group'       => 'stores',
            'description' => 'Edit store information',
        ]);

        $this->billingEdit = AdminPermission::create([
            'name'        => 'billing.edit',
            'group'       => 'billing',
            'description' => 'Edit billing records',
        ]);

        // viewerRole has storesView only
        AdminRolePermission::create([
            'admin_role_id'        => $this->viewerRole->id,
            'admin_permission_id'  => $this->storesView->id,
        ]);

        // customRole has storesEdit and billingEdit
        AdminRolePermission::create([
            'admin_role_id'        => $this->customRole->id,
            'admin_permission_id'  => $this->storesEdit->id,
        ]);
        AdminRolePermission::create([
            'admin_role_id'        => $this->customRole->id,
            'admin_permission_id'  => $this->billingEdit->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // isSuperAdmin
    // ══════════════════════════════════════════════════════════

    public function test_is_super_admin_returns_false_when_no_roles(): void
    {
        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        $this->assertFalse($this->user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_true_when_super_admin_role_assigned(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);

        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        $this->assertTrue($this->user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_non_super_admin_role(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        $this->assertFalse($this->user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_custom_role(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->customRole->id,
            'assigned_at'   => now(),
        ]);

        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        $this->assertFalse($this->user->isSuperAdmin());
    }

    public function test_is_super_admin_result_is_cached_for_same_instance(): void
    {
        // Clear cache and reset in-memory cached value
        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        // First call loads and caches
        $first = $this->user->isSuperAdmin();

        // Now assign super admin role in DB (but cache should return stale result)
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);

        // Second call should still return cached false (in-memory cache)
        $second = $this->user->isSuperAdmin();

        $this->assertEquals($first, $second);
        $this->assertFalse($second);
    }

    // ══════════════════════════════════════════════════════════
    // hasPermission
    // ══════════════════════════════════════════════════════════

    public function test_has_permission_returns_false_when_user_has_no_roles(): void
    {
        $this->assertFalse($this->user->hasPermission('stores.view'));
    }

    public function test_has_permission_returns_false_for_permission_not_in_any_role(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertFalse($this->user->hasPermission('billing.edit'));
    }

    public function test_has_permission_returns_true_when_role_grants_permission(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertTrue($this->user->hasPermission('stores.view'));
    }

    public function test_has_permission_returns_false_for_nonexistent_permission_name(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertFalse($this->user->hasPermission('does.not.exist'));
    }

    public function test_super_admin_has_any_permission_including_nonexistent(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        $this->assertTrue($this->user->hasPermission('stores.view'));
        $this->assertTrue($this->user->hasPermission('billing.edit'));
        $this->assertTrue($this->user->hasPermission('anything.at.all'));
    }

    // ══════════════════════════════════════════════════════════
    // hasPermissionTo (alias)
    // ══════════════════════════════════════════════════════════

    public function test_has_permission_to_is_alias_for_has_permission(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertTrue($this->user->hasPermissionTo('stores.view'));
        $this->assertFalse($this->user->hasPermissionTo('billing.edit'));
    }

    public function test_has_permission_to_accepts_optional_guard_parameter(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        // guardName is ignored (always checks admin-api guard)
        $this->assertTrue($this->user->hasPermissionTo('stores.view', 'web'));
    }

    // ══════════════════════════════════════════════════════════
    // hasAnyPermission
    // ══════════════════════════════════════════════════════════

    public function test_has_any_permission_returns_false_when_none_match(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertFalse($this->user->hasAnyPermission(['billing.edit', 'stores.edit']));
    }

    public function test_has_any_permission_returns_true_when_one_matches(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertTrue($this->user->hasAnyPermission(['stores.view', 'billing.edit']));
    }

    public function test_has_any_permission_returns_true_when_all_match(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->customRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertTrue($this->user->hasAnyPermission(['stores.edit', 'billing.edit']));
    }

    public function test_has_any_permission_returns_false_with_empty_array(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        $this->assertFalse($this->user->hasAnyPermission([]));
    }

    public function test_super_admin_has_any_permission_always_returns_true(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:is_super_admin");
        $this->user->cachedIsSuperAdmin = null;

        $this->assertTrue($this->user->hasAnyPermission(['nonexistent.perm.a', 'nonexistent.perm.b']));
    }

    // ══════════════════════════════════════════════════════════
    // Multi-role permission union
    // ══════════════════════════════════════════════════════════

    public function test_permissions_are_union_of_all_assigned_roles(): void
    {
        $multiUser = AdminUser::forceCreate([
            'name'          => 'Multi Role User',
            'email'         => 'multi@thawani.test',
            'password_hash' => bcrypt('pass_secure_123'),
            'is_active'     => true,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $multiUser->id,
            'admin_role_id' => $this->viewerRole->id,  // storesView
            'assigned_at'   => now(),
        ]);
        AdminUserRole::create([
            'admin_user_id' => $multiUser->id,
            'admin_role_id' => $this->customRole->id,  // storesEdit + billingEdit
            'assigned_at'   => now(),
        ]);

        $this->assertTrue($multiUser->hasPermission('stores.view'));
        $this->assertTrue($multiUser->hasPermission('stores.edit'));
        $this->assertTrue($multiUser->hasPermission('billing.edit'));
        $this->assertFalse($multiUser->hasPermission('billing.view'));
    }

    public function test_duplicate_permissions_across_roles_are_deduplicated(): void
    {
        // Give customRole also storesView (already in viewerRole)
        AdminRolePermission::create([
            'admin_role_id'       => $this->customRole->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        $multiUser = AdminUser::forceCreate([
            'name'          => 'Dup Perm User',
            'email'         => 'dup@thawani.test',
            'password_hash' => bcrypt('pass_secure_456'),
            'is_active'     => true,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $multiUser->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        AdminUserRole::create([
            'admin_user_id' => $multiUser->id,
            'admin_role_id' => $this->customRole->id,
            'assigned_at'   => now(),
        ]);

        // hasPermission should return true (not error from duplicates)
        $this->assertTrue($multiUser->hasPermission('stores.view'));
    }

    // ══════════════════════════════════════════════════════════
    // Attribute visibility
    // ══════════════════════════════════════════════════════════

    public function test_password_hash_is_hidden_from_array(): void
    {
        $array = $this->user->toArray();
        $this->assertArrayNotHasKey('password_hash', $array);
    }

    public function test_two_factor_secret_is_hidden_from_array(): void
    {
        $array = $this->user->toArray();
        $this->assertArrayNotHasKey('two_factor_secret', $array);
    }

    public function test_public_fields_are_visible_in_array(): void
    {
        $array = $this->user->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('is_active', $array);
    }

    // ══════════════════════════════════════════════════════════
    // Authentication
    // ══════════════════════════════════════════════════════════

    public function test_get_auth_password_returns_password_hash_value(): void
    {
        $hashed = bcrypt('my_secure_pass');
        $user   = AdminUser::forceCreate([
            'name'          => 'Auth Test User',
            'email'         => 'authtest@thawani.test',
            'password_hash' => $hashed,
            'is_active'     => true,
        ]);

        $this->assertEquals($hashed, $user->getAuthPassword());
    }

    public function test_get_auth_identifier_returns_id(): void
    {
        $this->assertNotEmpty($this->user->getAuthIdentifier());
        $this->assertEquals($this->user->id, $this->user->getAuthIdentifier());
    }

    // ══════════════════════════════════════════════════════════
    // canAccessPanel
    // ══════════════════════════════════════════════════════════

    public function test_can_access_panel_returns_true_for_active_user(): void
    {
        $panel = $this->createMock(Panel::class);
        $this->assertTrue($this->user->canAccessPanel($panel));
    }

    public function test_can_access_panel_returns_false_for_inactive_user(): void
    {
        $this->user->update(['is_active' => false]);
        $panel = $this->createMock(Panel::class);
        $this->assertFalse($this->user->canAccessPanel($panel));
    }

    // ══════════════════════════════════════════════════════════
    // Relationships
    // ══════════════════════════════════════════════════════════

    public function test_admin_user_roles_relation_returns_assigned_roles(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        $roles = $this->user->adminUserRoles;
        $this->assertCount(1, $roles);
        $this->assertEquals($this->viewerRole->id, $roles->first()->admin_role_id);
    }

    public function test_roles_belongs_to_many_returns_role_objects(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);

        $roles = $this->user->roles;
        $this->assertCount(1, $roles);
        $this->assertEquals('Super Admin', $roles->first()->name);
    }

    public function test_user_with_no_roles_has_empty_roles_collection(): void
    {
        $roles = $this->user->roles;
        $this->assertCount(0, $roles);
    }

    // ══════════════════════════════════════════════════════════
    // Model configuration
    // ══════════════════════════════════════════════════════════

    public function test_model_uses_uuid_primary_key(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $this->user->id
        );
    }

    public function test_model_table_is_admin_users(): void
    {
        $this->assertEquals('admin_users', (new AdminUser())->getTable());
    }

    public function test_admin_user_can_be_created_with_minimal_data(): void
    {
        $user = AdminUser::forceCreate([
            'name'          => 'Minimal User',
            'email'         => 'minimal@thawani.test',
            'password_hash' => bcrypt('secure_pass_123'),
            'is_active'     => true,
        ]);

        $this->assertNotNull($user->id);
        $this->assertNull($user->phone);
        $this->assertNull($user->avatar_url);
        $this->assertFalse($user->two_factor_enabled);
    }

    // ══════════════════════════════════════════════════════════
    // Cache invalidation behavior
    // ══════════════════════════════════════════════════════════

    public function test_permissions_cached_in_memory_for_same_instance(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");
        $this->user->cachedPermissions = null;

        // First call loads from DB
        $first = $this->user->hasPermission('stores.view');

        // Remove DB record (but in-memory cache remains)
        AdminRolePermission::where('admin_role_id', $this->viewerRole->id)->delete();

        // Second call should still return true (in-memory cache not cleared)
        $second = $this->user->hasPermission('stores.view');

        $this->assertTrue($first);
        $this->assertTrue($second); // cached result
    }

    public function test_fresh_instance_loads_updated_permissions(): void
    {
        AdminUserRole::create([
            'admin_user_id' => $this->user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        Cache::forget("admin_user:{$this->user->id}:permissions");

        $freshUser = AdminUser::find($this->user->id);
        $this->assertTrue($freshUser->hasPermission('stores.view'));
    }
}
