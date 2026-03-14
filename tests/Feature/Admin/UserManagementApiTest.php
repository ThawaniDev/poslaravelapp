<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $token;
    private Organization $org;
    private Store $store1;
    private Store $store2;
    private User $providerUser1;
    private User $providerUser2;
    private User $providerUser3;
    private AdminRole $role;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin
        $this->admin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        // Create organization
        $this->org = Organization::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Test Organization',
            'is_active' => true,
        ]);

        // Create stores
        $this->store1 = Store::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Store Alpha',
            'is_active' => true,
        ]);
        $this->store2 = Store::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Store Beta',
            'is_active' => true,
        ]);

        // Create provider users
        $this->providerUser1 = User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Ahmed Cashier',
            'email' => 'ahmed@store.com',
            'phone' => '96812345678',
            'password_hash' => bcrypt('password'),
            'role' => 'cashier',
            'organization_id' => $this->org->id,
            'store_id' => $this->store1->id,
            'is_active' => true,
        ]);

        $this->providerUser2 = User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Sara Manager',
            'email' => 'sara@store.com',
            'phone' => '96887654321',
            'password_hash' => bcrypt('password'),
            'role' => 'branch_manager',
            'organization_id' => $this->org->id,
            'store_id' => $this->store2->id,
            'is_active' => true,
        ]);

        $this->providerUser3 = User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Omar Owner',
            'email' => 'omar@store.com',
            'password_hash' => bcrypt('password'),
            'role' => 'owner',
            'organization_id' => $this->org->id,
            'store_id' => $this->store1->id,
            'is_active' => false,
        ]);

        // Create an admin role for invite tests
        $this->role = AdminRole::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Support Agent',
            'slug' => 'support-agent',
            'is_system' => false,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users — List
    // ═══════════════════════════════════════════════════════════════

    public function test_list_provider_users(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider', $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['users', 'pagination']]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data.users')));
    }

    public function test_list_provider_users_search_by_email(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider?search=ahmed@', $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(1, $users);
        $this->assertEquals('ahmed@store.com', $users[0]['email']);
    }

    public function test_list_provider_users_search_by_phone(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider?search=96887654321', $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(1, $users);
        $this->assertEquals('Sara Manager', $users[0]['name']);
    }

    public function test_list_provider_users_search_by_name(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider?search=Omar', $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(1, $users);
        $this->assertEquals('Omar Owner', $users[0]['name']);
    }

    public function test_list_provider_users_filter_by_store(): void
    {
        $response = $this->getJson("/api/v2/admin/users/provider?store_id={$this->store1->id}", $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(2, $users);
    }

    public function test_list_provider_users_filter_by_organization(): void
    {
        $response = $this->getJson("/api/v2/admin/users/provider?organization_id={$this->org->id}", $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(3, $users);
    }

    public function test_list_provider_users_filter_by_role(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider?role=cashier', $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(1, $users);
        $this->assertEquals('cashier', $users[0]['role']);
    }

    public function test_list_provider_users_filter_by_active(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider?is_active=false', $this->authHeaders());

        $response->assertOk();
        $users = $response->json('data.users');
        $this->assertCount(1, $users);
        $this->assertEquals('Omar Owner', $users[0]['name']);
    }

    public function test_list_provider_users_pagination(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider?per_page=2', $this->authHeaders());

        $response->assertOk();
        $pagination = $response->json('data.pagination');
        $this->assertEquals(2, $pagination['per_page']);
        $this->assertEquals(3, $pagination['total']);
    }

    public function test_list_provider_users_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider');

        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users — Show
    // ═══════════════════════════════════════════════════════════════

    public function test_show_provider_user(): void
    {
        $response = $this->getJson("/api/v2/admin/users/provider/{$this->providerUser1->id}", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.email', 'ahmed@store.com')
                 ->assertJsonPath('data.name', 'Ahmed Cashier')
                 ->assertJsonPath('data.is_active', true);
    }

    public function test_show_provider_user_includes_store_info(): void
    {
        $response = $this->getJson("/api/v2/admin/users/provider/{$this->providerUser1->id}", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('data.store_id', $this->store1->id);
    }

    public function test_show_provider_user_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->getJson("/api/v2/admin/users/provider/{$fakeId}", $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users — Reset Password
    // ═══════════════════════════════════════════════════════════════

    public function test_reset_password(): void
    {
        $response = $this->postJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/reset-password", [], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['temporary_password', 'must_change_password']])
                 ->assertJsonPath('data.must_change_password', true);

        // Verify password was changed
        $user = User::find($this->providerUser1->id);
        $this->assertTrue($user->must_change_password);
    }

    public function test_reset_password_creates_activity_log(): void
    {
        $this->postJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/reset-password", [], $this->authHeaders());

        $log = AdminActivityLog::where('entity_type', 'user')
            ->where('entity_id', $this->providerUser1->id)
            ->where('action', 'reset_password')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_reset_password_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->postJson("/api/v2/admin/users/provider/{$fakeId}/reset-password", [], $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users — Force Password Change
    // ═══════════════════════════════════════════════════════════════

    public function test_force_password_change(): void
    {
        $response = $this->postJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/force-password-change", [], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true);

        $user = User::find($this->providerUser1->id);
        $this->assertTrue($user->must_change_password);
    }

    public function test_force_password_change_creates_log(): void
    {
        $this->postJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/force-password-change", [], $this->authHeaders());

        $log = AdminActivityLog::where('action', 'force_password_change')
            ->where('entity_id', $this->providerUser1->id)
            ->first();

        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users — Toggle Active
    // ═══════════════════════════════════════════════════════════════

    public function test_toggle_provider_active_disable(): void
    {
        $response = $this->postJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/toggle-active", [], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('data.is_active', false);

        $user = User::find($this->providerUser1->id);
        $this->assertFalse($user->is_active);
    }

    public function test_toggle_provider_active_enable(): void
    {
        // providerUser3 is inactive
        $response = $this->postJson("/api/v2/admin/users/provider/{$this->providerUser3->id}/toggle-active", [], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('data.is_active', true);

        $user = User::find($this->providerUser3->id);
        $this->assertTrue($user->is_active);
    }

    public function test_toggle_active_creates_log(): void
    {
        $this->postJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/toggle-active", [], $this->authHeaders());

        $log = AdminActivityLog::where('action', 'user_disabled')
            ->where('entity_id', $this->providerUser1->id)
            ->first();

        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    // Provider Users — Activity Log
    // ═══════════════════════════════════════════════════════════════

    public function test_provider_user_activity(): void
    {
        // Create some logs first
        AdminActivityLog::forceCreate([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $this->admin->id,
            'action' => 'reset_password',
            'entity_type' => 'user',
            'entity_id' => $this->providerUser1->id,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/activity", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('data.user_id', $this->providerUser1->id)
                 ->assertJsonStructure(['data' => ['user_id', 'logs']]);

        $this->assertCount(1, $response->json('data.logs'));
    }

    public function test_provider_user_activity_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->getJson("/api/v2/admin/users/provider/{$fakeId}/activity", $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users — List
    // ═══════════════════════════════════════════════════════════════

    public function test_list_admin_users(): void
    {
        $response = $this->getJson('/api/v2/admin/users/admins', $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['admins']]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data.admins')));
    }

    public function test_list_admin_users_search(): void
    {
        // Create another admin
        AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Support Person',
            'email' => 'support@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/admin/users/admins?search=Support', $this->authHeaders());

        $response->assertOk();
        $admins = $response->json('data.admins');
        $this->assertCount(1, $admins);
        $this->assertEquals('Support Person', $admins[0]['name']);
    }

    public function test_list_admin_users_filter_active(): void
    {
        // Create inactive admin
        AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Inactive Admin',
            'email' => 'inactive@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v2/admin/users/admins?is_active=false', $this->authHeaders());

        $response->assertOk();
        $admins = $response->json('data.admins');
        $this->assertCount(1, $admins);
        $this->assertEquals('Inactive Admin', $admins[0]['name']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users — Show
    // ═══════════════════════════════════════════════════════════════

    public function test_show_admin_user(): void
    {
        $response = $this->getJson("/api/v2/admin/users/admins/{$this->admin->id}", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.email', 'admin@test.com')
                 ->assertJsonPath('data.name', 'Test Admin');
    }

    public function test_show_admin_user_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->getJson("/api/v2/admin/users/admins/{$fakeId}", $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users — Invite
    // ═══════════════════════════════════════════════════════════════

    public function test_invite_admin(): void
    {
        $response = $this->postJson('/api/v2/admin/users/admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'phone' => '+968 1234567',
            'role_ids' => [$this->role->id],
        ], $this->authHeaders());

        $response->assertCreated()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'New Admin')
                 ->assertJsonPath('data.email', 'newadmin@test.com');

        // Verify role assignment
        $newAdmin = AdminUser::where('email', 'newadmin@test.com')->first();
        $this->assertNotNull($newAdmin);
        $this->assertEquals(1, AdminUserRole::where('admin_user_id', $newAdmin->id)->count());
    }

    public function test_invite_admin_creates_activity_log(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [
            'name' => 'Log Admin',
            'email' => 'logadmin@test.com',
            'role_ids' => [$this->role->id],
        ], $this->authHeaders());

        $log = AdminActivityLog::where('action', 'admin_invited')
            ->where('entity_type', 'admin_user')
            ->first();

        $this->assertNotNull($log);
    }

    public function test_invite_admin_validation_missing_name(): void
    {
        $response = $this->postJson('/api/v2/admin/users/admins', [
            'email' => 'noname@test.com',
            'role_ids' => [$this->role->id],
        ], $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_invite_admin_validation_missing_email(): void
    {
        $response = $this->postJson('/api/v2/admin/users/admins', [
            'name' => 'No Email',
            'role_ids' => [$this->role->id],
        ], $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_invite_admin_validation_duplicate_email(): void
    {
        $response = $this->postJson('/api/v2/admin/users/admins', [
            'name' => 'Duplicate',
            'email' => 'admin@test.com', // already exists
            'role_ids' => [$this->role->id],
        ], $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_invite_admin_validation_missing_roles(): void
    {
        $response = $this->postJson('/api/v2/admin/users/admins', [
            'name' => 'No Roles',
            'email' => 'noroles@test.com',
        ], $this->authHeaders());

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users — Update
    // ═══════════════════════════════════════════════════════════════

    public function test_update_admin(): void
    {
        $targetAdmin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Old Name',
            'email' => 'target@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v2/admin/users/admins/{$targetAdmin->id}", [
            'name' => 'Updated Name',
            'phone' => '+968 9999999',
        ], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('data.name', 'Updated Name');

        $updated = AdminUser::find($targetAdmin->id);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('+968 9999999', $updated->phone);
    }

    public function test_update_admin_roles(): void
    {
        $targetAdmin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Role User',
            'email' => 'roleuser@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $role2 = AdminRole::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Manager',
            'slug' => 'manager',
            'is_system' => false,
        ]);

        $response = $this->putJson("/api/v2/admin/users/admins/{$targetAdmin->id}", [
            'role_ids' => [$this->role->id, $role2->id],
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertEquals(2, AdminUserRole::where('admin_user_id', $targetAdmin->id)->count());
    }

    public function test_update_admin_self_deactivation_blocked(): void
    {
        $response = $this->putJson("/api/v2/admin/users/admins/{$this->admin->id}", [
            'is_active' => false,
        ], $this->authHeaders());

        $response->assertStatus(422);

        // Verify still active
        $admin = AdminUser::find($this->admin->id);
        $this->assertTrue($admin->is_active);
    }

    public function test_update_admin_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->putJson("/api/v2/admin/users/admins/{$fakeId}", [
            'name' => 'Ghost',
        ], $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users — Reset 2FA
    // ═══════════════════════════════════════════════════════════════

    public function test_reset_2fa(): void
    {
        $targetAdmin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => '2FA User',
            'email' => '2fa@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
            'two_factor_secret' => 'some-secret',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson("/api/v2/admin/users/admins/{$targetAdmin->id}/reset-2fa", [], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.two_factor_enabled', false);

        $updated = AdminUser::find($targetAdmin->id);
        $this->assertNull($updated->two_factor_secret);
        $this->assertFalse($updated->two_factor_enabled);
        $this->assertNull($updated->two_factor_confirmed_at);
    }

    public function test_reset_2fa_creates_log(): void
    {
        $targetAdmin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => '2FA Log User',
            'email' => '2falog@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
            'two_factor_enabled' => true,
        ]);

        $this->postJson("/api/v2/admin/users/admins/{$targetAdmin->id}/reset-2fa", [], $this->authHeaders());

        $log = AdminActivityLog::where('action', 'admin_2fa_reset')
            ->where('entity_id', $targetAdmin->id)
            ->first();

        $this->assertNotNull($log);
    }

    public function test_reset_2fa_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->postJson("/api/v2/admin/users/admins/{$fakeId}/reset-2fa", [], $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin Users — Activity Log
    // ═══════════════════════════════════════════════════════════════

    public function test_admin_user_activity(): void
    {
        // Create logs by the admin
        AdminActivityLog::forceCreate([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $this->admin->id,
            'action' => 'login',
            'entity_type' => 'admin_user',
            'entity_id' => $this->admin->id,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/users/admins/{$this->admin->id}/activity", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonPath('data.admin_user_id', $this->admin->id)
                 ->assertJsonStructure(['data' => ['admin_user_id', 'logs']]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data.logs')));
    }

    public function test_admin_user_activity_not_found(): void
    {
        $fakeId = (string) Str::uuid();
        $response = $this->getJson("/api/v2/admin/users/admins/{$fakeId}/activity", $this->authHeaders());

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // Resource Structure Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_provider_user_resource_structure(): void
    {
        $response = $this->getJson("/api/v2/admin/users/provider/{$this->providerUser1->id}", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonStructure(['data' => [
                     'id', 'name', 'email', 'phone', 'role', 'locale',
                     'is_active', 'must_change_password',
                     'store_id', 'organization_id',
                     'created_at', 'updated_at',
                 ]]);
    }

    public function test_admin_user_detail_resource_structure(): void
    {
        $response = $this->getJson("/api/v2/admin/users/admins/{$this->admin->id}", $this->authHeaders());

        $response->assertOk()
                 ->assertJsonStructure(['data' => [
                     'id', 'name', 'email', 'is_active',
                     'two_factor_enabled', 'roles',
                     'last_login_at', 'last_login_ip',
                     'created_at', 'updated_at',
                 ]]);
    }

    public function test_activity_log_resource_structure(): void
    {
        AdminActivityLog::forceCreate([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $this->admin->id,
            'action' => 'test_action',
            'entity_type' => 'user',
            'entity_id' => $this->providerUser1->id,
            'details' => ['key' => 'value'],
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/users/provider/{$this->providerUser1->id}/activity", $this->authHeaders());

        $response->assertOk();
        $log = $response->json('data.logs.0');
        $this->assertArrayHasKey('id', $log);
        $this->assertArrayHasKey('action', $log);
        $this->assertArrayHasKey('entity_type', $log);
        $this->assertArrayHasKey('ip_address', $log);
        $this->assertArrayHasKey('created_at', $log);
    }
}
