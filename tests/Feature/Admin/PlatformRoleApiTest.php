<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformRoleApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private AdminRole $superAdminRole;
    private AdminRole $viewerRole;
    private AdminPermission $storesView;
    private AdminPermission $storesEdit;
    private AdminPermission $billingView;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');

        // Create system roles
        $this->superAdminRole = AdminRole::create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'description' => 'Full platform control',
            'is_system' => true,
        ]);

        $this->viewerRole = AdminRole::create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'description' => 'Read-only access',
            'is_system' => true,
        ]);

        // Create permissions
        $this->storesView = AdminPermission::forceCreate([
            'name' => 'stores.view',
            'group' => 'stores',
            'description' => 'View store list and details',
            'created_at' => now(),
        ]);

        $this->storesEdit = AdminPermission::forceCreate([
            'name' => 'stores.edit',
            'group' => 'stores',
            'description' => 'Edit store settings',
            'created_at' => now(),
        ]);

        $this->billingView = AdminPermission::forceCreate([
            'name' => 'billing.view',
            'group' => 'billing',
            'description' => 'View billing data',
            'created_at' => now(),
        ]);

        // Assign permissions to super admin role
        AdminRolePermission::create([
            'admin_role_id' => $this->superAdminRole->id,
            'admin_permission_id' => $this->storesView->id,
        ]);
        AdminRolePermission::create([
            'admin_role_id' => $this->superAdminRole->id,
            'admin_permission_id' => $this->storesEdit->id,
        ]);
        AdminRolePermission::create([
            'admin_role_id' => $this->superAdminRole->id,
            'admin_permission_id' => $this->billingView->id,
        ]);

        // Assign role to admin
        AdminUserRole::create([
            'admin_user_id' => $this->admin->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at' => now(),
        ]);
    }

    // ─── Roles: List ─────────────────────────────────────────

    public function test_list_roles_returns_all_roles(): void
    {
        $response = $this->getJson('/api/v2/admin/roles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.roles.0.name', 'Super Admin')
            ->assertJsonCount(2, 'data.roles');
    }

    public function test_list_roles_includes_user_and_permission_counts(): void
    {
        $response = $this->getJson('/api/v2/admin/roles');

        $response->assertOk();
        $roles = $response->json('data.roles');
        $superAdmin = collect($roles)->firstWhere('slug', 'super_admin');

        $this->assertEquals(1, $superAdmin['users_count']);
        $this->assertEquals(3, $superAdmin['permissions_count']);
    }

    public function test_list_roles_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v2/admin/roles');

        $response->assertUnauthorized();
    }

    // ─── Roles: Show ─────────────────────────────────────────

    public function test_show_role_returns_role_with_permissions(): void
    {
        $response = $this->getJson("/api/v2/admin/roles/{$this->superAdminRole->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'Super Admin')
            ->assertJsonPath('data.role.is_system', true)
            ->assertJsonCount(3, 'data.role.permissions');
    }

    public function test_show_role_returns_404_for_missing_role(): void
    {
        $fakeId = fake()->uuid();

        $response = $this->getJson("/api/v2/admin/roles/{$fakeId}");

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ─── Roles: Create ───────────────────────────────────────

    public function test_create_custom_role(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'Content Manager',
            'description' => 'Manages content and announcements',
            'permission_ids' => [$this->storesView->id, $this->billingView->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'Content Manager')
            ->assertJsonPath('data.role.is_system', false)
            ->assertJsonPath('data.role.permissions_count', 2);

        $this->assertDatabaseHas('admin_roles', [
            'name' => 'Content Manager',
            'is_system' => false,
        ]);
    }

    public function test_create_role_generates_slug_from_name(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'Content Manager',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('admin_roles', [
            'slug' => 'content_manager',
        ]);
    }

    public function test_create_role_with_custom_slug(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'Content Manager',
            'slug' => 'content_mgr',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('admin_roles', [
            'slug' => 'content_mgr',
        ]);
    }

    public function test_create_role_requires_name(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'description' => 'No name',
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_role_validates_unique_slug(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'Dupe Slug',
            'slug' => 'super_admin',
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_role_validates_permission_ids_exist(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'Bad Perms',
            'permission_ids' => [fake()->uuid()],
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_role_logs_activity(): void
    {
        $this->postJson('/api/v2/admin/roles', [
            'name' => 'Logged Role',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'role.create',
            'entity_type' => 'admin_role',
        ]);
    }

    // ─── Roles: Update ───────────────────────────────────────

    public function test_update_custom_role(): void
    {
        $role = AdminRole::create([
            'name' => 'Old Name',
            'slug' => 'old_name',
            'is_system' => false,
        ]);

        $response = $this->putJson("/api/v2/admin/roles/{$role->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
            'permission_ids' => [$this->storesView->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'New Name')
            ->assertJsonPath('data.role.permissions_count', 1);
    }

    public function test_cannot_rename_system_role(): void
    {
        $response = $this->putJson("/api/v2/admin/roles/{$this->superAdminRole->id}", [
            'name' => 'Renamed Super',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_can_update_system_role_permissions(): void
    {
        $response = $this->putJson("/api/v2/admin/roles/{$this->viewerRole->id}", [
            'permission_ids' => [$this->storesView->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role.permissions_count', 1);
    }

    public function test_update_role_returns_404_for_missing_role(): void
    {
        $response = $this->putJson('/api/v2/admin/roles/' . fake()->uuid(), [
            'name' => 'Doesnt Exist',
        ]);

        $response->assertNotFound();
    }

    public function test_update_role_logs_activity(): void
    {
        $role = AdminRole::create([
            'name' => 'To Update',
            'slug' => 'to_update',
            'is_system' => false,
        ]);

        $this->putJson("/api/v2/admin/roles/{$role->id}", [
            'name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'role.update',
            'entity_type' => 'admin_role',
            'entity_id' => $role->id,
        ]);
    }

    // ─── Roles: Delete ───────────────────────────────────────

    public function test_delete_custom_role(): void
    {
        $role = AdminRole::create([
            'name' => 'To Delete',
            'slug' => 'to_delete',
            'is_system' => false,
        ]);

        $response = $this->deleteJson("/api/v2/admin/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('admin_roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_system_role(): void
    {
        $response = $this->deleteJson("/api/v2/admin/roles/{$this->superAdminRole->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $role = AdminRole::create([
            'name' => 'Users Assigned',
            'slug' => 'users_assigned',
            'is_system' => false,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $this->admin->id,
            'admin_role_id' => $role->id,
            'assigned_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v2/admin/roles/{$role->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('admin_roles', ['id' => $role->id]);
    }

    public function test_delete_role_returns_404_for_missing(): void
    {
        $response = $this->deleteJson('/api/v2/admin/roles/' . fake()->uuid());

        $response->assertNotFound();
    }

    public function test_delete_role_logs_activity(): void
    {
        $role = AdminRole::create([
            'name' => 'Log Delete',
            'slug' => 'log_delete',
            'is_system' => false,
        ]);

        $this->deleteJson("/api/v2/admin/roles/{$role->id}");

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'role.delete',
            'entity_type' => 'admin_role',
            'entity_id' => $role->id,
        ]);
    }

    // ─── Permissions ─────────────────────────────────────────

    public function test_list_permissions_grouped(): void
    {
        $response = $this->getJson('/api/v2/admin/permissions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 3);

        $permissions = $response->json('data.permissions');
        $this->assertArrayHasKey('stores', $permissions);
        $this->assertArrayHasKey('billing', $permissions);
        $this->assertCount(2, $permissions['stores']);
        $this->assertCount(1, $permissions['billing']);
    }

    public function test_permissions_include_details(): void
    {
        $response = $this->getJson('/api/v2/admin/permissions');

        $storePerms = $response->json('data.permissions.stores');
        $first = $storePerms[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('description', $first);
    }

    // ─── Admin Team: List ────────────────────────────────────

    public function test_list_team_users(): void
    {
        $response = $this->getJson('/api/v2/admin/team');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.users')
            ->assertJsonStructure([
                'data' => [
                    'users' => [['id', 'name', 'email', 'is_active']],
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ],
            ]);
    }

    public function test_list_team_with_search(): void
    {
        AdminUser::forceCreate([
            'name' => 'Jane Doe',
            'email' => 'jane@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/admin/team?search=jane');

        $response->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.name', 'Jane Doe');
    }

    public function test_list_team_filter_by_active(): void
    {
        AdminUser::forceCreate([
            'name' => 'Disabled User',
            'email' => 'disabled@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v2/admin/team?is_active=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data.users');
    }

    public function test_list_team_filter_by_role(): void
    {
        $otherAdmin = AdminUser::forceCreate([
            'name' => 'Other Admin',
            'email' => 'other@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $otherAdmin->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/team?role_id={$this->viewerRole->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.email', 'other@thawani.test');
    }

    // ─── Admin Team: Show ────────────────────────────────────

    public function test_show_team_user(): void
    {
        $response = $this->getJson("/api/v2/admin/team/{$this->admin->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Super Admin')
            ->assertJsonPath('data.user.email', 'admin@thawani.test');
    }

    public function test_show_team_user_includes_roles(): void
    {
        $response = $this->getJson("/api/v2/admin/team/{$this->admin->id}");

        $response->assertOk();
        $roles = $response->json('data.user.roles');
        $this->assertNotEmpty($roles);
        $this->assertEquals('Super Admin', $roles[0]['name']);
    }

    public function test_show_team_user_returns_404(): void
    {
        $response = $this->getJson('/api/v2/admin/team/' . fake()->uuid());

        $response->assertNotFound();
    }

    // ─── Admin Team: Create ──────────────────────────────────

    public function test_create_team_user(): void
    {
        $response = $this->postJson('/api/v2/admin/team', [
            'name' => 'New Admin',
            'email' => 'newadmin@thawani.test',
            'password' => 'SuperSecure123!',
            'role_ids' => [$this->viewerRole->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'New Admin')
            ->assertJsonPath('data.user.email', 'newadmin@thawani.test');

        $this->assertDatabaseHas('admin_users', [
            'email' => 'newadmin@thawani.test',
        ]);
        $this->assertDatabaseHas('admin_user_roles', [
            'admin_role_id' => $this->viewerRole->id,
        ]);
    }

    public function test_create_team_user_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v2/admin/team', [
            'name' => 'No Email',
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_team_user_validates_unique_email(): void
    {
        $response = $this->postJson('/api/v2/admin/team', [
            'name' => 'Dupe Email',
            'email' => 'admin@thawani.test',
            'password' => 'SuperSecure123!',
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_team_user_validates_password_min_length(): void
    {
        $response = $this->postJson('/api/v2/admin/team', [
            'name' => 'Short Pass',
            'email' => 'shortpass@thawani.test',
            'password' => 'short',
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_team_user_logs_activity(): void
    {
        $this->postJson('/api/v2/admin/team', [
            'name' => 'Logged User',
            'email' => 'logged@thawani.test',
            'password' => 'SuperSecure123!',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'admin_user.create',
            'entity_type' => 'admin_user',
        ]);
    }

    // ─── Admin Team: Update ──────────────────────────────────

    public function test_update_team_user(): void
    {
        $target = AdminUser::forceCreate([
            'name' => 'Old Name',
            'email' => 'target@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v2/admin/team/{$target->id}", [
            'name' => 'Updated Name',
            'phone' => '+968-99999999',
            'role_ids' => [$this->viewerRole->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Updated Name');
    }

    public function test_update_team_user_returns_404(): void
    {
        $response = $this->putJson('/api/v2/admin/team/' . fake()->uuid(), [
            'name' => 'Nonexistent',
        ]);

        $response->assertNotFound();
    }

    public function test_update_team_user_logs_activity(): void
    {
        $target = AdminUser::forceCreate([
            'name' => 'Target',
            'email' => 'target2@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->putJson("/api/v2/admin/team/{$target->id}", [
            'name' => 'Updated Target',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'admin_user.update',
            'entity_id' => $target->id,
        ]);
    }

    // ─── Admin Team: Deactivate & Activate ───────────────────

    public function test_deactivate_team_user(): void
    {
        $target = AdminUser::forceCreate([
            'name' => 'To Deactivate',
            'email' => 'deactivate@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v2/admin/team/{$target->id}/deactivate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.is_active', false);

        $this->assertDatabaseHas('admin_users', [
            'id' => $target->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_deactivate_self(): void
    {
        $response = $this->postJson("/api/v2/admin/team/{$this->admin->id}/deactivate");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_deactivate_logs_activity(): void
    {
        $target = AdminUser::forceCreate([
            'name' => 'Log Deactivate',
            'email' => 'logdeactivate@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->postJson("/api/v2/admin/team/{$target->id}/deactivate");

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'admin_user.deactivate',
            'entity_id' => $target->id,
        ]);
    }

    public function test_activate_team_user(): void
    {
        $target = AdminUser::forceCreate([
            'name' => 'To Activate',
            'email' => 'activate@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->postJson("/api/v2/admin/team/{$target->id}/activate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.is_active', true);
    }

    public function test_activate_returns_404_for_missing(): void
    {
        $response = $this->postJson('/api/v2/admin/team/' . fake()->uuid() . '/activate');

        $response->assertNotFound();
    }

    // ─── Profile: Me ─────────────────────────────────────────

    public function test_me_returns_current_admin_profile(): void
    {
        $response = $this->getJson('/api/v2/admin/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.name', 'Super Admin')
            ->assertJsonPath('data.profile.email', 'admin@thawani.test');
    }

    public function test_me_includes_roles_and_permissions(): void
    {
        $response = $this->getJson('/api/v2/admin/me');

        $response->assertOk();
        $profile = $response->json('data.profile');

        $this->assertArrayHasKey('roles', $profile);
        $this->assertArrayHasKey('permissions', $profile);
        $this->assertNotEmpty($profile['roles']);
        $this->assertContains('stores.view', $profile['permissions']);
        $this->assertContains('stores.edit', $profile['permissions']);
        $this->assertContains('billing.view', $profile['permissions']);
    }

    // ─── Activity Log ────────────────────────────────────────

    public function test_list_activity_logs(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'test.action',
            'entity_type' => 'test_entity',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'logs' => [['id', 'action', 'entity_type', 'ip_address', 'created_at']],
                    'pagination',
                ],
            ]);
    }

    public function test_filter_activity_logs_by_action(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'role.create',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'store.suspend',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log?action=role.create');

        $response->assertOk();
        $logs = $response->json('data.logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('role.create', $logs[0]['action']);
    }

    public function test_filter_activity_logs_by_entity_type(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'role.create',
            'entity_type' => 'admin_role',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'store.suspend',
            'entity_type' => 'store',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log?entity_type=admin_role');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.logs'));
    }

    public function test_filter_activity_logs_by_admin_user(): void
    {
        $otherAdmin = AdminUser::forceCreate([
            'name' => 'Other Admin',
            'email' => 'otheradmin@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'test.action1',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $otherAdmin->id,
            'action' => 'test.action2',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/activity-log?admin_user_id={$otherAdmin->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.logs'));
        $this->assertEquals('test.action2', $response->json('data.logs.0.action'));
    }

    public function test_activity_log_includes_admin_user_name(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->admin->id,
            'action' => 'test.named',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log');

        $response->assertOk();
        $this->assertEquals('Super Admin', $response->json('data.logs.0.admin_user_name'));
    }

    public function test_activity_log_pagination(): void
    {
        for ($i = 0; $i < 25; $i++) {
            AdminActivityLog::forceCreate([
                'admin_user_id' => $this->admin->id,
                'action' => "test.action{$i}",
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/activity-log?per_page=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data.logs'));
        $this->assertEquals(25, $response->json('data.pagination.total'));
        $this->assertEquals(3, $response->json('data.pagination.last_page'));
    }

    // ─── Edge Cases ──────────────────────────────────────────

    public function test_create_role_without_permissions(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'Empty Role',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role.permissions_count', 0);
    }

    public function test_update_role_clears_permissions_with_empty_array(): void
    {
        $role = AdminRole::create([
            'name' => 'Has Perms',
            'slug' => 'has_perms',
            'is_system' => false,
        ]);
        AdminRolePermission::create([
            'admin_role_id' => $role->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        $response = $this->putJson("/api/v2/admin/roles/{$role->id}", [
            'permission_ids' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role.permissions_count', 0);
    }

    public function test_create_user_with_multiple_roles(): void
    {
        $response = $this->postJson('/api/v2/admin/team', [
            'name' => 'Multi Role',
            'email' => 'multirole@thawani.test',
            'password' => 'SuperSecure123!',
            'role_ids' => [$this->superAdminRole->id, $this->viewerRole->id],
        ]);

        $response->assertCreated();
        $roles = $response->json('data.user.roles');
        $this->assertCount(2, $roles);
    }

    public function test_multiple_role_permissions_union(): void
    {
        // Create a second admin with both roles
        $multiUser = AdminUser::forceCreate([
            'name' => 'Multi Role Admin',
            'email' => 'multi@thawani.test',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Viewer role gets only billing.view
        AdminRolePermission::create([
            'admin_role_id' => $this->viewerRole->id,
            'admin_permission_id' => $this->billingView->id,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $multiUser->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at' => now(),
        ]);
        AdminUserRole::create([
            'admin_user_id' => $multiUser->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at' => now(),
        ]);

        // Auth as multi-user
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($multiUser, ['*'], 'admin-api');

        $response = $this->getJson('/api/v2/admin/me');

        $response->assertOk();
        $permissions = $response->json('data.profile.permissions');
        // Union of super_admin (stores.view, stores.edit, billing.view) + viewer (billing.view)
        $this->assertContains('stores.view', $permissions);
        $this->assertContains('stores.edit', $permissions);
        $this->assertContains('billing.view', $permissions);
        // Deduped
        $this->assertEquals(count($permissions), count(array_unique($permissions)));
    }

    // ─── all_permissions key (Flutter compatibility) ─────────

    public function test_me_includes_all_permissions_key_for_flutter(): void
    {
        $response = $this->getJson('/api/v2/admin/me');

        $response->assertOk();
        $profile = $response->json('data.profile');

        $this->assertArrayHasKey('all_permissions', $profile);
        $this->assertIsArray($profile['all_permissions']);
        $this->assertContains('stores.view', $profile['all_permissions']);
    }

    // ─── Permission Enforcement ───────────────────────────────

    public function test_create_role_forbidden_without_admin_team_roles_permission(): void
    {
        // Create admin with only a viewer role (no admin_team.roles perm)
        $viewerAdmin = AdminUser::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $viewerAdmin->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at' => now(),
        ]);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($viewerAdmin, ['*'], 'admin-api');

        $response = $this->postJson('/api/v2/admin/roles', [
            'name' => 'New Role',
        ]);

        $response->assertForbidden();
    }

    public function test_create_team_user_forbidden_without_admin_team_manage_permission(): void
    {
        $viewerAdmin = AdminUser::forceCreate([
            'name' => 'Viewer2',
            'email' => 'viewer2@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $viewerAdmin->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at' => now(),
        ]);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($viewerAdmin, ['*'], 'admin-api');

        $response = $this->postJson('/api/v2/admin/team', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'password123456!',
        ]);

        $response->assertForbidden();
    }

    public function test_list_roles_allowed_with_admin_team_roles_permission(): void
    {
        // The default test admin is a super admin, so can list roles
        $response = $this->getJson('/api/v2/admin/roles');
        $response->assertOk()->assertJsonPath('success', true);
    }
}
