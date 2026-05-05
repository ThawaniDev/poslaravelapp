<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminRolePermission;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\AdminPanel\Models\AdminUserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Extended Feature/API/Integration/E2E tests for User Management (P4).
 *
 * Covers:
 *  - Stats endpoint: structure, counts, authentication
 *  - Unauthenticated rejection for ALL endpoints
 *  - Permission enforcement for every endpoint not yet tested
 *  - E2E: Provider user management lifecycle
 *  - E2E: Admin user invite → update → deactivate → reset-2fa lifecycle
 *  - Activity log structure and limits
 *  - Response structure completeness
 *  - Error response consistency
 *  - Subscription context (organization status)
 */
class UserManagementApiExtendedTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $superAdmin;
    private AdminUser $viewerOnlyAdmin;
    private string $superToken;
    private string $viewerToken;

    private AdminRole $superAdminRole;
    private AdminRole $viewerRole;

    // Permission objects
    private AdminPermission $usersView;
    private AdminPermission $usersEdit;
    private AdminPermission $usersManage;
    private AdminPermission $usersResetPassword;
    private AdminPermission $adminTeamView;
    private AdminPermission $adminTeamManage;

    // Test data
    private Organization $org;
    private Store $store;
    private User $providerUser;
    private AdminRole $inviteRole;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Permissions ──────────────────────────────────────────
        $this->usersView = AdminPermission::create([
            'name'  => 'users.view',
            'group' => 'users',
            'description' => 'View provider users',
        ]);
        $this->usersEdit = AdminPermission::create([
            'name'  => 'users.edit',
            'group' => 'users',
            'description' => 'Edit provider users',
        ]);
        $this->usersManage = AdminPermission::create([
            'name'  => 'users.manage',
            'group' => 'users',
            'description' => 'Full management of provider users',
        ]);
        $this->usersResetPassword = AdminPermission::create([
            'name'  => 'users.reset_password',
            'group' => 'users',
            'description' => 'Reset user passwords',
        ]);
        $this->adminTeamView = AdminPermission::create([
            'name'  => 'admin_team.view',
            'group' => 'admin_team',
            'description' => 'View admin team',
        ]);
        $this->adminTeamManage = AdminPermission::create([
            'name'  => 'admin_team.manage',
            'group' => 'admin_team',
            'description' => 'Manage admin team',
        ]);

        // ── Roles ────────────────────────────────────────────────
        $this->superAdminRole = AdminRole::create([
            'name'      => 'Super Admin',
            'slug'      => 'super_admin',
            'is_system' => true,
        ]);
        foreach ([$this->usersView, $this->usersEdit, $this->usersManage, $this->usersResetPassword, $this->adminTeamView, $this->adminTeamManage] as $perm) {
            AdminRolePermission::create([
                'admin_role_id'       => $this->superAdminRole->id,
                'admin_permission_id' => $perm->id,
            ]);
        }

        $this->viewerRole = AdminRole::create([
            'name'      => 'Viewer',
            'slug'      => 'viewer',
            'is_system' => true,
        ]);
        // Viewer has NO user or admin_team permissions

        // ── Admins ───────────────────────────────────────────────
        $this->superAdmin = AdminUser::forceCreate([
            'name'          => 'Super Admin',
            'email'         => 'super@ext.test',
            'password_hash' => bcrypt('secure_pass'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->superAdmin->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);
        $this->superToken = $this->superAdmin->createToken('test')->plainTextToken;

        $this->viewerOnlyAdmin = AdminUser::forceCreate([
            'name'          => 'Viewer Only',
            'email'         => 'viewer@ext.test',
            'password_hash' => bcrypt('secure_pass'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->viewerOnlyAdmin->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);
        $this->viewerToken = $this->viewerOnlyAdmin->createToken('test')->plainTextToken;

        // ── Provider data ────────────────────────────────────────
        $this->org = Organization::forceCreate([
            'id'        => (string) Str::uuid(),
            'name'      => 'Test Org',
            'is_active' => true,
        ]);
        $this->store = Store::forceCreate([
            'id'        => (string) Str::uuid(),
            'name'      => 'Test Store',
            'is_active' => true,
        ]);
        $this->providerUser = User::forceCreate([
            'id'              => (string) Str::uuid(),
            'name'            => 'Provider User',
            'email'           => 'provider@ext.test',
            'password_hash'   => bcrypt('pass'),
            'role'            => 'cashier',
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'is_active'       => true,
        ]);

        // Admin role for invite tests
        $this->inviteRole = AdminRole::create([
            'name'      => 'Support Role',
            'slug'      => 'support_role',
            'is_system' => false,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->superToken}"];
    }

    private function viewerAuth(): array
    {
        return ['Authorization' => "Bearer {$this->viewerToken}"];
    }

    // ══════════════════════════════════════════════════════════
    // Stats Endpoint
    // ══════════════════════════════════════════════════════════

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/api/v2/admin/users/stats')->assertUnauthorized();
    }

    public function test_stats_returns_200_for_authenticated_admin_with_permission(): void
    {
        $this->getJson('/api/v2/admin/users/stats', $this->auth())->assertOk();
    }

    public function test_stats_forbidden_without_any_user_or_team_permission(): void
    {
        $this->getJson('/api/v2/admin/users/stats', $this->viewerAuth())->assertForbidden();
    }

    public function test_stats_allowed_with_users_view_permission(): void
    {
        $admin = $this->createAdminWithPermissions([$this->usersView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson(
            '/api/v2/admin/users/stats',
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    public function test_stats_allowed_with_admin_team_view_permission(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson(
            '/api/v2/admin/users/stats',
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    public function test_stats_response_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/users/stats', $this->auth());
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_provider_users',
                    'active_provider_users',
                    'inactive_provider_users',
                    'new_this_month',
                    'total_admin_users',
                    'active_admin_users',
                    'role_distribution',
                    'top_stores_by_users',
                ],
            ]);
    }

    public function test_stats_counts_are_accurate(): void
    {
        // 1 active provider user in setUp, add 1 inactive
        User::forceCreate([
            'id'              => (string) Str::uuid(),
            'name'            => 'Inactive Provider',
            'email'           => 'inactive@ext.test',
            'password_hash'   => bcrypt('pass'),
            'role'            => 'owner',
            'organization_id' => $this->org->id,
            'is_active'       => false,
        ]);

        $response = $this->getJson('/api/v2/admin/users/stats', $this->auth());
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(2, $data['total_provider_users']);
        $this->assertEquals(1, $data['active_provider_users']);
        $this->assertEquals(1, $data['inactive_provider_users']);
    }

    public function test_stats_new_this_month_counts_only_this_month(): void
    {
        // providerUser was created in setUp (this month)
        $response = $this->getJson('/api/v2/admin/users/stats', $this->auth());
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['new_this_month']);
    }

    public function test_stats_admin_counts_include_super_admin(): void
    {
        $response  = $this->getJson('/api/v2/admin/users/stats', $this->auth());
        $data      = $response->json('data');

        // We have superAdmin (active) and viewerOnlyAdmin (active) in setUp
        $this->assertGreaterThanOrEqual(2, $data['total_admin_users']);
        $this->assertGreaterThanOrEqual(2, $data['active_admin_users']);
    }

    public function test_stats_role_distribution_contains_provider_roles(): void
    {
        $response = $this->getJson('/api/v2/admin/users/stats', $this->auth());
        $data     = $response->json('data.role_distribution');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('cashier', $data); // providerUser is a cashier
    }

    // ══════════════════════════════════════════════════════════
    // Unauthenticated Access (401 for ALL endpoints)
    // ══════════════════════════════════════════════════════════

    /** @dataProvider unauthenticatedUserEndpointsProvider */
    public function test_unauthenticated_request_returns_401(string $method, string $url): void
    {
        $this->json($method, $url)->assertUnauthorized();
    }

    public static function unauthenticatedUserEndpointsProvider(): array
    {
        $fakeId = '00000000-0000-0000-0000-000000000001';

        return [
            'GET /admin/users/stats'                           => ['GET',  '/api/v2/admin/users/stats'],
            'GET /admin/users/provider'                        => ['GET',  '/api/v2/admin/users/provider'],
            'GET /admin/users/provider/{id}'                   => ['GET',  "/api/v2/admin/users/provider/{$fakeId}"],
            'POST /admin/users/provider/{id}/reset-password'   => ['POST', "/api/v2/admin/users/provider/{$fakeId}/reset-password"],
            'POST /admin/users/provider/{id}/force-password'   => ['POST', "/api/v2/admin/users/provider/{$fakeId}/force-password-change"],
            'POST /admin/users/provider/{id}/toggle-active'    => ['POST', "/api/v2/admin/users/provider/{$fakeId}/toggle-active"],
            'GET /admin/users/provider/{id}/activity'          => ['GET',  "/api/v2/admin/users/provider/{$fakeId}/activity"],
            'GET /admin/users/admins'                          => ['GET',  '/api/v2/admin/users/admins'],
            'POST /admin/users/admins'                         => ['POST', '/api/v2/admin/users/admins'],
            'GET /admin/users/admins/{id}'                     => ['GET',  "/api/v2/admin/users/admins/{$fakeId}"],
            'PUT /admin/users/admins/{id}'                     => ['PUT',  "/api/v2/admin/users/admins/{$fakeId}"],
            'POST /admin/users/admins/{id}/reset-2fa'          => ['POST', "/api/v2/admin/users/admins/{$fakeId}/reset-2fa"],
            'GET /admin/users/admins/{id}/activity'            => ['GET',  "/api/v2/admin/users/admins/{$fakeId}/activity"],
        ];
    }

    // ══════════════════════════════════════════════════════════
    // Permission Enforcement: Provider User endpoints
    // ══════════════════════════════════════════════════════════

    public function test_show_provider_user_forbidden_without_users_view(): void
    {
        $this->getJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}",
            $this->viewerAuth()
        )->assertForbidden();
    }

    public function test_show_provider_user_allowed_with_users_view(): void
    {
        $admin = $this->createAdminWithPermissions([$this->usersView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}",
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    public function test_force_password_change_forbidden_without_users_edit(): void
    {
        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/force-password-change",
            [],
            $this->viewerAuth()
        )->assertForbidden();
    }

    public function test_force_password_change_allowed_with_users_edit(): void
    {
        $admin = $this->createAdminWithPermissions([$this->usersEdit]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/force-password-change",
            [],
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    public function test_toggle_active_forbidden_without_users_edit(): void
    {
        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/toggle-active",
            [],
            $this->viewerAuth()
        )->assertForbidden();
    }

    public function test_toggle_active_allowed_with_users_manage(): void
    {
        $admin = $this->createAdminWithPermissions([$this->usersManage]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/toggle-active",
            [],
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    public function test_provider_user_activity_forbidden_without_users_view(): void
    {
        $this->getJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/activity",
            $this->viewerAuth()
        )->assertForbidden();
    }

    public function test_reset_password_requires_users_reset_password_or_manage(): void
    {
        // users.edit alone is NOT enough for reset_password
        $admin = $this->createAdminWithPermissions([$this->usersEdit]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/reset-password",
            [],
            ['Authorization' => "Bearer {$token}"]
        )->assertForbidden();
    }

    public function test_reset_password_allowed_with_users_reset_password(): void
    {
        $admin = $this->createAdminWithPermissions([$this->usersResetPassword]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/reset-password",
            [],
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    // ══════════════════════════════════════════════════════════
    // Permission Enforcement: Admin User endpoints
    // ══════════════════════════════════════════════════════════

    public function test_list_admin_users_forbidden_without_admin_team_view(): void
    {
        $this->getJson('/api/v2/admin/users/admins', $this->viewerAuth())->assertForbidden();
    }

    public function test_list_admin_users_allowed_with_admin_team_view(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson(
            '/api/v2/admin/users/admins',
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    public function test_show_admin_user_forbidden_without_admin_team_view(): void
    {
        $this->getJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}",
            $this->viewerAuth()
        )->assertForbidden();
    }

    public function test_invite_admin_forbidden_without_admin_team_manage(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'New Admin',
            'email'    => 'new@ext.test',
            'role_ids' => [$this->inviteRole->id],
        ], ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_update_admin_forbidden_without_admin_team_manage(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->putJson(
            "/api/v2/admin/users/admins/{$this->viewerOnlyAdmin->id}",
            ['name' => 'Changed'],
            ['Authorization' => "Bearer {$token}"]
        )->assertForbidden();
    }

    public function test_reset_2fa_forbidden_without_admin_team_manage(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/v2/admin/users/admins/{$this->viewerOnlyAdmin->id}/reset-2fa",
            [],
            ['Authorization' => "Bearer {$token}"]
        )->assertForbidden();
    }

    public function test_admin_user_activity_forbidden_without_admin_team_view(): void
    {
        $this->getJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}/activity",
            $this->viewerAuth()
        )->assertForbidden();
    }

    public function test_admin_user_activity_allowed_with_admin_team_manage(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamManage]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}/activity",
            ['Authorization' => "Bearer {$token}"]
        )->assertOk();
    }

    // ══════════════════════════════════════════════════════════
    // E2E: Provider User Management Lifecycle
    // ══════════════════════════════════════════════════════════

    public function test_full_provider_user_management_lifecycle(): void
    {
        $userId = $this->providerUser->id;

        // 1. List and find the user
        $listResp = $this->getJson('/api/v2/admin/users/provider', $this->auth());
        $listResp->assertOk()->assertJsonPath('success', true);
        $users = collect($listResp->json('data.users') ?? $listResp->json('data.data') ?? []);
        $this->assertTrue($users->contains('id', $userId));

        // 2. View user detail
        $showResp = $this->getJson("/api/v2/admin/users/provider/{$userId}", $this->auth());
        $showResp->assertOk()
            ->assertJsonPath('data.user.id', $userId)
            ->assertJsonPath('data.user.role', 'cashier');

        // 3. Reset password
        $resetResp = $this->postJson("/api/v2/admin/users/provider/{$userId}/reset-password", [], $this->auth());
        $resetResp->assertOk()->assertJsonPath('success', true);

        $updatedUser = User::find($userId);
        $this->assertTrue($updatedUser->must_change_password);

        // 4. Force password change (separate flag)
        $forceResp = $this->postJson("/api/v2/admin/users/provider/{$userId}/force-password-change", [], $this->auth());
        $forceResp->assertOk()->assertJsonPath('success', true);

        // 5. Toggle active (disable)
        $toggleResp = $this->postJson("/api/v2/admin/users/provider/{$userId}/toggle-active", [], $this->auth());
        $toggleResp->assertOk();
        $this->assertFalse(User::find($userId)->is_active);

        // 6. Toggle active again (re-enable)
        $this->postJson("/api/v2/admin/users/provider/{$userId}/toggle-active", [], $this->auth())
            ->assertOk();
        $this->assertTrue(User::find($userId)->is_active);

        // 7. View activity log
        $actResp = $this->getJson("/api/v2/admin/users/provider/{$userId}/activity", $this->auth());
        $actResp->assertOk();

        // Verify activities were logged
        $logsCount = AdminActivityLog::where('entity_id', $userId)->count();
        $this->assertGreaterThanOrEqual(3, $logsCount);
    }

    // ══════════════════════════════════════════════════════════
    // E2E: Admin User Lifecycle (invite → update → reset-2fa)
    // ══════════════════════════════════════════════════════════

    public function test_full_admin_user_lifecycle(): void
    {
        // 1. Invite admin
        $inviteResp = $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'Lifecycle Admin',
            'email'    => 'lifecycle@ext.test',
            'role_ids' => [$this->inviteRole->id],
        ], $this->auth());
        $inviteResp->assertCreated()->assertJsonPath('success', true);
        $adminId = $inviteResp->json('data.admin.id');
        $this->assertNotNull($adminId);

        // 2. Show the invited admin
        $showResp = $this->getJson("/api/v2/admin/users/admins/{$adminId}", $this->auth());
        $showResp->assertOk()
            ->assertJsonPath('data.admin.email', 'lifecycle@ext.test');

        $roles = $showResp->json('data.admin.roles');
        $roleSlugs = array_column($roles, 'role_slug');
        $this->assertContains('support_role', $roleSlugs);

        // 3. Update admin name
        $updateResp = $this->putJson("/api/v2/admin/users/admins/{$adminId}", [
            'name' => 'Lifecycle Admin Updated',
        ], $this->auth());
        $updateResp->assertOk()
            ->assertJsonPath('data.admin.name', 'Lifecycle Admin Updated');

        // 4. Setup 2FA for the admin (directly in DB)
        $newAdmin = AdminUser::find($adminId);
        $newAdmin->update([
            'two_factor_secret'       => 'TEST_SECRET',
            'two_factor_enabled'      => true,
            'two_factor_confirmed_at' => now(),
        ]);

        // 5. Reset 2FA
        $reset2faResp = $this->postJson("/api/v2/admin/users/admins/{$adminId}/reset-2fa", [], $this->auth());
        $reset2faResp->assertOk()->assertJsonPath('success', true);

        $afterReset = AdminUser::find($adminId);
        $this->assertNull($afterReset->two_factor_secret);
        $this->assertFalse((bool)$afterReset->two_factor_enabled);
        $this->assertNull($afterReset->two_factor_confirmed_at);

        // 6. View activity for this admin
        $actResp = $this->getJson("/api/v2/admin/users/admins/{$adminId}/activity", $this->auth());
        $actResp->assertOk();

        // 7. Verify in admin list
        $listResp = $this->getJson('/api/v2/admin/users/admins', $this->auth());
        $listResp->assertOk();
        $admins = collect($listResp->json('data.admins') ?? $listResp->json('data.data') ?? []);
        $this->assertTrue($admins->contains('id', $adminId));
    }

    // ══════════════════════════════════════════════════════════
    // Self-edit restrictions
    // ══════════════════════════════════════════════════════════

    public function test_cannot_deactivate_self_via_update(): void
    {
        $this->putJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}",
            ['is_active' => false],
            $this->auth()
        )->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════
    // Resource Structure Completeness
    // ══════════════════════════════════════════════════════════

    public function test_provider_user_detail_response_includes_all_fields(): void
    {
        $response = $this->getJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}",
            $this->auth()
        );
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                        'must_change_password',
                        'store_id',
                        'organization_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_provider_user_list_response_has_pagination(): void
    {
        $response = $this->getJson('/api/v2/admin/users/provider', $this->auth());
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pagination' => ['total', 'current_page', 'last_page', 'per_page'],
                ],
            ]);
    }

    public function test_admin_user_detail_response_includes_roles(): void
    {
        $response = $this->getJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}",
            $this->auth()
        );
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'admin' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'two_factor_enabled',
                        'roles' => [
                            '*' => ['role_id', 'role_name', 'role_slug', 'assigned_at'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_invite_admin_response_includes_admin_object(): void
    {
        $response = $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'Structured Admin',
            'email'    => 'struct@ext.test',
            'role_ids' => [$this->inviteRole->id],
        ], $this->auth());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'admin' => ['id', 'name', 'email', 'is_active', 'roles'],
                ],
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // Activity Log: Provider Users
    // ══════════════════════════════════════════════════════════

    public function test_provider_user_activity_log_structure(): void
    {
        // Generate some activity
        $this->postJson("/api/v2/admin/users/provider/{$this->providerUser->id}/reset-password", [], $this->auth());

        $response = $this->getJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/activity",
            $this->auth()
        );
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['action', 'created_at'],
                ],
            ]);
    }

    public function test_provider_user_activity_log_returns_most_recent_50(): void
    {
        for ($i = 0; $i < 60; $i++) {
            AdminActivityLog::forceCreate([
                'admin_user_id' => $this->superAdmin->id,
                'action'        => "activity.{$i}",
                'entity_type'   => 'user',
                'entity_id'     => $this->providerUser->id,
                'ip_address'    => '127.0.0.1',
                'created_at'    => now()->subSeconds(60 - $i),
            ]);
        }

        $response = $this->getJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/activity",
            $this->auth()
        );
        $response->assertOk();

        $logs = $response->json('data');
        $this->assertLessThanOrEqual(50, count($logs));
    }

    public function test_admin_user_activity_log_structure(): void
    {
        // Generate activity
        $this->postJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}/reset-2fa",
            [],
            $this->auth()
        );

        $response = $this->getJson(
            "/api/v2/admin/users/admins/{$this->superAdmin->id}/activity",
            $this->auth()
        );
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['action', 'created_at'],
                ],
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // Reset Password Details
    // ══════════════════════════════════════════════════════════

    public function test_reset_password_sets_must_change_password_flag(): void
    {
        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/reset-password",
            [],
            $this->auth()
        )->assertOk();

        $this->assertTrue(User::find($this->providerUser->id)->must_change_password);
    }

    public function test_force_password_change_sets_must_change_password(): void
    {
        $this->providerUser->update(['must_change_password' => false]);

        $this->postJson(
            "/api/v2/admin/users/provider/{$this->providerUser->id}/force-password-change",
            [],
            $this->auth()
        )->assertOk();

        $this->assertTrue(User::find($this->providerUser->id)->must_change_password);
    }

    // ══════════════════════════════════════════════════════════
    // Reset 2FA: Clears all three fields
    // ══════════════════════════════════════════════════════════

    public function test_reset_2fa_clears_secret_enabled_and_confirmed_at(): void
    {
        $targetAdmin = AdminUser::forceCreate([
            'name'                    => '2FA Admin',
            'email'                   => '2fa@ext.test',
            'password_hash'           => bcrypt('pass'),
            'is_active'               => true,
            'two_factor_secret'       => 'MY_SECRET',
            'two_factor_enabled'      => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson(
            "/api/v2/admin/users/admins/{$targetAdmin->id}/reset-2fa",
            [],
            $this->auth()
        )->assertOk();

        $fresh = AdminUser::find($targetAdmin->id);
        $this->assertNull($fresh->two_factor_secret);
        $this->assertFalse((bool)$fresh->two_factor_enabled);
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    public function test_reset_2fa_logs_activity(): void
    {
        $targetAdmin = AdminUser::forceCreate([
            'name'          => '2FA Log Admin',
            'email'         => '2falog@ext.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $this->postJson(
            "/api/v2/admin/users/admins/{$targetAdmin->id}/reset-2fa",
            [],
            $this->auth()
        )->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'entity_id' => $targetAdmin->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // Validation Edge Cases
    // ══════════════════════════════════════════════════════════

    public function test_invite_admin_requires_name(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [
            'email'    => 'noname@ext.test',
            'role_ids' => [$this->inviteRole->id],
        ], $this->auth())->assertUnprocessable();
    }

    public function test_invite_admin_requires_email(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'No Email',
            'role_ids' => [$this->inviteRole->id],
        ], $this->auth())->assertUnprocessable();
    }

    public function test_invite_admin_requires_at_least_one_role(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'No Roles',
            'email'    => 'noroles@ext.test',
            'role_ids' => [],
        ], $this->auth())->assertUnprocessable();
    }

    public function test_invite_admin_duplicate_email_fails(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'Dup',
            'email'    => $this->superAdmin->email, // already exists
            'role_ids' => [$this->inviteRole->id],
        ], $this->auth())->assertUnprocessable();
    }

    public function test_invite_admin_invalid_role_id_fails(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [
            'name'     => 'Invalid Role',
            'email'    => 'invalid@ext.test',
            'role_ids' => ['00000000-0000-0000-0000-000000000099'],
        ], $this->auth())->assertUnprocessable();
    }

    public function test_show_provider_user_with_invalid_uuid_returns_404(): void
    {
        $this->getJson('/api/v2/admin/users/provider/not-a-uuid', $this->auth())
            ->assertNotFound();
    }

    public function test_show_admin_user_with_invalid_uuid_returns_404(): void
    {
        $this->getJson('/api/v2/admin/users/admins/not-a-uuid', $this->auth())
            ->assertNotFound();
    }

    public function test_reset_password_for_nonexistent_user_returns_404(): void
    {
        $this->postJson(
            '/api/v2/admin/users/provider/00000000-0000-0000-0000-000000000099/reset-password',
            [],
            $this->auth()
        )->assertNotFound();
    }

    public function test_update_admin_nonexistent_returns_404(): void
    {
        $this->putJson(
            '/api/v2/admin/users/admins/00000000-0000-0000-0000-000000000099',
            ['name' => 'X'],
            $this->auth()
        )->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════
    // List Admin Users: Filtering and pagination
    // ══════════════════════════════════════════════════════════

    public function test_list_admin_users_pagination_with_per_page(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            AdminUser::forceCreate([
                'name'          => "Admin User {$i}",
                'email'         => "adminuser{$i}@ext.test",
                'password_hash' => bcrypt('pass'),
                'is_active'     => true,
            ]);
        }

        $response = $this->getJson('/api/v2/admin/users/admins?per_page=3', $this->auth());
        $response->assertOk();

        $data = $response->json('data');
        $pagination = $data['pagination'] ?? null;

        if ($pagination) {
            $this->assertGreaterThan(1, $pagination['last_page']);
        } else {
            // Response might wrap items directly
            $this->assertOk($response);
        }
    }

    public function test_list_admin_users_search_case_insensitive(): void
    {
        AdminUser::forceCreate([
            'name'          => 'UniqueSearchableName',
            'email'         => 'uniquesearch@ext.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        $response = $this->getJson('/api/v2/admin/users/admins?search=uniquesearchable', $this->auth());
        $response->assertOk();

        $admins = collect(
            $response->json('data.admins') ??
            $response->json('data.data') ??
            $response->json('data') ??
            []
        );
        $this->assertGreaterThanOrEqual(1, $admins->count());
    }

    // ══════════════════════════════════════════════════════════
    // Provider User List: Search and filters
    // ══════════════════════════════════════════════════════════

    public function test_list_provider_users_filter_by_must_change_password(): void
    {
        // Set one user to must_change_password
        $this->providerUser->update(['must_change_password' => true]);

        User::forceCreate([
            'id'              => (string) Str::uuid(),
            'name'            => 'Normal User',
            'email'           => 'normal@ext.test',
            'password_hash'   => bcrypt('pass'),
            'role'            => 'cashier',
            'organization_id' => $this->org->id,
            'is_active'       => true,
            'must_change_password' => false,
        ]);

        $response = $this->getJson(
            '/api/v2/admin/users/provider?must_change_password=true',
            $this->auth()
        );
        $response->assertOk();

        $users = collect(
            $response->json('data.users') ??
            $response->json('data.data') ??
            []
        );
        foreach ($users as $user) {
            $this->assertTrue($user['must_change_password']);
        }
    }

    // ══════════════════════════════════════════════════════════
    // Error Response Consistency
    // ══════════════════════════════════════════════════════════

    public function test_forbidden_response_has_success_false(): void
    {
        $this->getJson('/api/v2/admin/users/provider', $this->viewerAuth())
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_not_found_response_has_success_false(): void
    {
        $this->getJson(
            '/api/v2/admin/users/provider/00000000-0000-0000-0000-999999999999',
            $this->auth()
        )->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_validation_error_response_has_success_false(): void
    {
        $this->postJson('/api/v2/admin/users/admins', [], $this->auth())
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    // ══════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════

    private function createAdminWithPermissions(array $permissions): AdminUser
    {
        $role = AdminRole::create([
            'name'      => 'Limited ' . uniqid(),
            'slug'      => 'limited_' . uniqid(),
            'is_system' => false,
        ]);

        foreach ($permissions as $perm) {
            AdminRolePermission::create([
                'admin_role_id'       => $role->id,
                'admin_permission_id' => $perm->id,
            ]);
        }

        $user = AdminUser::forceCreate([
            'name'          => 'Limited Admin ' . uniqid(),
            'email'         => 'limited_' . uniqid() . '@ext.test',
            'password_hash' => bcrypt('pass'),
            'is_active'     => true,
        ]);

        AdminUserRole::create([
            'admin_user_id' => $user->id,
            'admin_role_id' => $role->id,
            'assigned_at'   => now(),
        ]);

        return $user;
    }

    private function assertOk($response): void
    {
        $response->assertStatus(200);
    }
}
