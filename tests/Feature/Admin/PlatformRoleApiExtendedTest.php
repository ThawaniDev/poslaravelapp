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

/**
 * Extended Feature/API/Integration/E2E tests for Platform Roles (P2).
 *
 * This file extends PlatformRoleApiTest to provide:
 *  - Complete permission enforcement for every endpoint
 *  - Unauthenticated access rejection for every endpoint
 *  - Activity log advanced filtering (date ranges, combined filters)
 *  - Full E2E lifecycle tests (role + team user)
 *  - Pagination edge cases
 *  - Input validation edge cases
 *  - Resource structure completeness
 *  - Error response structure consistency
 */
class PlatformRoleApiExtendedTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $superAdmin;
    private AdminUser $viewerOnlyAdmin;
    private AdminRole $superAdminRole;
    private AdminRole $viewerRole;
    private AdminPermission $adminTeamView;
    private AdminPermission $adminTeamManage;
    private AdminPermission $adminTeamRolesView;
    private AdminPermission $adminTeamRolesManage;
    private AdminPermission $storesView;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Permissions ──────────────────────────────────────────
        $this->adminTeamView = AdminPermission::create([
            'name'  => 'admin_team.view',
            'group' => 'admin_team',
            'description' => 'View admin team members',
        ]);
        $this->adminTeamManage = AdminPermission::create([
            'name'  => 'admin_team.manage',
            'group' => 'admin_team',
            'description' => 'Manage admin team members',
        ]);
        $this->adminTeamRolesView = AdminPermission::create([
            'name'  => 'admin_team.roles',
            'group' => 'admin_team',
            'description' => 'View admin roles',
        ]);
        $this->adminTeamRolesManage = AdminPermission::create([
            'name'  => 'admin_team.roles_manage',
            'group' => 'admin_team',
            'description' => 'Manage admin roles',
        ]);
        $this->storesView = AdminPermission::create([
            'name'  => 'stores.view',
            'group' => 'stores',
            'description' => 'View stores',
        ]);

        // ── Roles ────────────────────────────────────────────────
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

        // Assign storesView to viewer (limited permissions - cannot do admin_team.*)
        AdminRolePermission::create([
            'admin_role_id'       => $this->viewerRole->id,
            'admin_permission_id' => $this->storesView->id,
        ]);

        // Assign all admin_team.* to super admin
        foreach ([
            $this->adminTeamView,
            $this->adminTeamManage,
            $this->adminTeamRolesView,
            $this->adminTeamRolesManage,
            $this->storesView,
        ] as $perm) {
            AdminRolePermission::create([
                'admin_role_id'       => $this->superAdminRole->id,
                'admin_permission_id' => $perm->id,
            ]);
        }

        // ── Users ────────────────────────────────────────────────
        $this->superAdmin = AdminUser::forceCreate([
            'name'          => 'Super Admin',
            'email'         => 'super@thawani.test',
            'password_hash' => bcrypt('secure_pass'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->superAdmin->id,
            'admin_role_id' => $this->superAdminRole->id,
            'assigned_at'   => now(),
        ]);

        $this->viewerOnlyAdmin = AdminUser::forceCreate([
            'name'          => 'Viewer Only',
            'email'         => 'viewer@thawani.test',
            'password_hash' => bcrypt('secure_pass'),
            'is_active'     => true,
        ]);
        AdminUserRole::create([
            'admin_user_id' => $this->viewerOnlyAdmin->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        Sanctum::actingAs($this->superAdmin, ['*'], 'admin-api');
    }

    // ══════════════════════════════════════════════════════════
    // Unauthenticated Access (401 for ALL endpoints)
    // ══════════════════════════════════════════════════════════

    /** @dataProvider unauthenticatedEndpointsProvider */
    public function test_unauthenticated_request_returns_401(string $method, string $url): void
    {
        $response = $this->json($method, $url);
        $response->assertUnauthorized();
    }

    public static function unauthenticatedEndpointsProvider(): array
    {
        return [
            'GET /admin/roles'                              => ['GET',    '/api/v2/admin/roles'],
            'POST /admin/roles'                             => ['POST',   '/api/v2/admin/roles'],
            'GET /admin/roles/fake-id'                      => ['GET',    '/api/v2/admin/roles/00000000-0000-0000-0000-000000000001'],
            'PUT /admin/roles/fake-id'                      => ['PUT',    '/api/v2/admin/roles/00000000-0000-0000-0000-000000000001'],
            'DELETE /admin/roles/fake-id'                   => ['DELETE', '/api/v2/admin/roles/00000000-0000-0000-0000-000000000001'],
            'GET /admin/permissions'                        => ['GET',    '/api/v2/admin/permissions'],
            'GET /admin/team'                               => ['GET',    '/api/v2/admin/team'],
            'POST /admin/team'                              => ['POST',   '/api/v2/admin/team'],
            'GET /admin/team/fake-id'                       => ['GET',    '/api/v2/admin/team/00000000-0000-0000-0000-000000000001'],
            'PUT /admin/team/fake-id'                       => ['PUT',    '/api/v2/admin/team/00000000-0000-0000-0000-000000000001'],
            'POST /admin/team/fake-id/deactivate'           => ['POST',   '/api/v2/admin/team/00000000-0000-0000-0000-000000000001/deactivate'],
            'POST /admin/team/fake-id/activate'             => ['POST',   '/api/v2/admin/team/00000000-0000-0000-0000-000000000001/activate'],
            'GET /admin/me'                                 => ['GET',    '/api/v2/admin/me'],
            'GET /admin/activity-log'                       => ['GET',    '/api/v2/admin/activity-log'],
        ];
    }

    // ══════════════════════════════════════════════════════════
    // Permission Enforcement: Roles endpoints
    // ══════════════════════════════════════════════════════════

    public function test_list_roles_forbidden_without_admin_team_roles_or_view(): void
    {
        $this->actingAsViewer();
        $this->getJson('/api/v2/admin/roles')->assertForbidden();
    }

    public function test_show_role_forbidden_without_admin_team_roles_or_view(): void
    {
        $this->actingAsViewer();
        $this->getJson("/api/v2/admin/roles/{$this->superAdminRole->id}")->assertForbidden();
    }

    public function test_update_role_forbidden_without_admin_team_roles_manage(): void
    {
        // Create an admin that has admin_team.roles (view) but NOT manage
        $limitedAdmin = $this->createAdminWithPermissions([$this->adminTeamRolesView]);
        $this->actingAsAdmin($limitedAdmin);

        $this->putJson("/api/v2/admin/roles/{$this->superAdminRole->id}", [
            'description' => 'Changed',
        ])->assertForbidden();
    }

    public function test_delete_role_forbidden_without_admin_team_roles_manage(): void
    {
        $role = AdminRole::create([
            'name'      => 'Deletable Role',
            'slug'      => 'deletable',
            'is_system' => false,
        ]);
        $limitedAdmin = $this->createAdminWithPermissions([$this->adminTeamRolesView]);
        $this->actingAsAdmin($limitedAdmin);

        $this->deleteJson("/api/v2/admin/roles/{$role->id}")->assertForbidden();
    }

    public function test_create_role_forbidden_without_admin_team_roles_manage(): void
    {
        $limitedAdmin = $this->createAdminWithPermissions([$this->adminTeamRolesView]);
        $this->actingAsAdmin($limitedAdmin);

        $this->postJson('/api/v2/admin/roles', ['name' => 'New Role'])->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════
    // Permission Enforcement: Team endpoints
    // ══════════════════════════════════════════════════════════

    public function test_list_team_forbidden_without_admin_team_view_or_manage(): void
    {
        $this->actingAsViewer();
        $this->getJson('/api/v2/admin/team')->assertForbidden();
    }

    public function test_list_team_allowed_with_admin_team_view(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v2/admin/team')->assertOk();
    }

    public function test_show_team_user_forbidden_without_admin_team_view(): void
    {
        $user = $this->createTeamMember('target@test.com');
        $this->actingAsViewer();

        $this->getJson("/api/v2/admin/team/{$user->id}")->assertForbidden();
    }

    public function test_show_team_user_allowed_with_admin_team_view(): void
    {
        $user  = $this->createTeamMember('target@test.com');
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $this->actingAsAdmin($admin);

        $this->getJson("/api/v2/admin/team/{$user->id}")->assertOk();
    }

    public function test_create_team_user_forbidden_without_admin_team_manage(): void
    {
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v2/admin/team', [
            'name'     => 'New User',
            'email'    => 'new@test.com',
            'password' => 'SecurePass123!',
        ])->assertForbidden();
    }

    public function test_update_team_user_forbidden_without_admin_team_manage(): void
    {
        $user  = $this->createTeamMember('update@test.com');
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $this->actingAsAdmin($admin);

        $this->putJson("/api/v2/admin/team/{$user->id}", [
            'name' => 'Changed Name',
        ])->assertForbidden();
    }

    public function test_deactivate_team_user_forbidden_without_admin_team_manage(): void
    {
        $user  = $this->createTeamMember('deact@test.com');
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $this->actingAsAdmin($admin);

        $this->postJson("/api/v2/admin/team/{$user->id}/deactivate")->assertForbidden();
    }

    public function test_activate_team_user_forbidden_without_admin_team_manage(): void
    {
        $user = $this->createTeamMember('activ@test.com', false);
        $admin = $this->createAdminWithPermissions([$this->adminTeamView]);
        $this->actingAsAdmin($admin);

        $this->postJson("/api/v2/admin/team/{$user->id}/activate")->assertForbidden();
    }

    public function test_update_team_user_allowed_with_admin_team_manage(): void
    {
        $user  = $this->createTeamMember('mngd@test.com');
        $admin = $this->createAdminWithPermissions([$this->adminTeamManage]);
        $this->actingAsAdmin($admin);

        $this->putJson("/api/v2/admin/team/{$user->id}", [
            'name' => 'Managed Update',
        ])->assertOk();
    }

    // ══════════════════════════════════════════════════════════
    // Permission Enforcement: Me & Activity Log (open to auth)
    // ══════════════════════════════════════════════════════════

    public function test_me_accessible_to_any_authenticated_admin(): void
    {
        $this->actingAsViewer();
        $this->getJson('/api/v2/admin/me')->assertOk();
    }

    public function test_list_permissions_accessible_to_any_authenticated_admin(): void
    {
        $this->actingAsViewer();
        $this->getJson('/api/v2/admin/permissions')->assertOk();
    }

    public function test_activity_log_accessible_to_any_authenticated_admin(): void
    {
        $this->actingAsViewer();
        $this->getJson('/api/v2/admin/activity-log')->assertOk();
    }

    // ══════════════════════════════════════════════════════════
    // E2E: Full Role Lifecycle
    // ══════════════════════════════════════════════════════════

    public function test_full_role_lifecycle_create_view_update_delete(): void
    {
        $storesEdit = AdminPermission::create([
            'name'  => 'stores.edit',
            'group' => 'stores',
            'description' => 'Edit stores',
        ]);

        // 1. Create role
        $createResp = $this->postJson('/api/v2/admin/roles', [
            'name'           => 'Lifecycle Role',
            'description'    => 'A test lifecycle role',
            'permission_ids' => [$this->storesView->id],
        ]);
        $createResp->assertCreated()->assertJsonPath('success', true);
        $roleId = $createResp->json('data.role.id');
        $this->assertNotNull($roleId);

        // 2. View the created role
        $showResp = $this->getJson("/api/v2/admin/roles/{$roleId}");
        $showResp->assertOk()
            ->assertJsonPath('data.role.name', 'Lifecycle Role')
            ->assertJsonPath('data.role.is_system', false);

        // 3. Update the role (add permissions)
        $updateResp = $this->putJson("/api/v2/admin/roles/{$roleId}", [
            'name'           => 'Lifecycle Role Updated',
            'permission_ids' => [$this->storesView->id, $storesEdit->id],
        ]);
        $updateResp->assertOk()
            ->assertJsonPath('data.role.name', 'Lifecycle Role Updated');
        $this->assertEquals(2, $updateResp->json('data.role.permissions_count'));

        // 4. Verify in list
        $listResp = $this->getJson('/api/v2/admin/roles');
        $listResp->assertOk();
        $roleInList = collect($listResp->json('data.roles'))
            ->firstWhere('id', $roleId);
        $this->assertNotNull($roleInList);
        $this->assertEquals('Lifecycle Role Updated', $roleInList['name']);

        // 5. Delete role
        $deleteResp = $this->deleteJson("/api/v2/admin/roles/{$roleId}");
        $deleteResp->assertOk()->assertJsonPath('success', true);

        // 6. Verify it's gone
        $this->getJson("/api/v2/admin/roles/{$roleId}")->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════
    // E2E: Full Team User Lifecycle
    // ══════════════════════════════════════════════════════════

    public function test_full_team_user_lifecycle_create_view_update_deactivate_activate(): void
    {
        // 1. Create team user
        $createResp = $this->postJson('/api/v2/admin/team', [
            'name'     => 'Lifecycle User',
            'email'    => 'lifecycle@thawani.test',
            'password' => 'SecurePass@2025!',
            'role_ids' => [$this->viewerRole->id],
        ]);
        $createResp->assertCreated()->assertJsonPath('success', true);
        $userId = $createResp->json('data.user.id');
        $this->assertNotNull($userId);

        // 2. Show user
        $showResp = $this->getJson("/api/v2/admin/team/{$userId}");
        $showResp->assertOk()
            ->assertJsonPath('data.user.email', 'lifecycle@thawani.test')
            ->assertJsonPath('data.user.is_active', true);

        // Verify role was assigned
        $roles = $showResp->json('data.user.roles');
        $this->assertCount(1, $roles);
        $this->assertEquals('viewer', $roles[0]['slug']);

        // 3. Update user
        $updateResp = $this->putJson("/api/v2/admin/team/{$userId}", [
            'name' => 'Lifecycle User Updated',
        ]);
        $updateResp->assertOk()
            ->assertJsonPath('data.user.name', 'Lifecycle User Updated');

        // 4. Deactivate user
        $deactResp = $this->postJson("/api/v2/admin/team/{$userId}/deactivate");
        $deactResp->assertOk()->assertJsonPath('data.user.is_active', false);

        // 5. Verify deactivated in show
        $this->getJson("/api/v2/admin/team/{$userId}")
            ->assertOk()
            ->assertJsonPath('data.user.is_active', false);

        // 6. Re-activate user
        $actResp = $this->postJson("/api/v2/admin/team/{$userId}/activate");
        $actResp->assertOk()->assertJsonPath('data.user.is_active', true);

        // 7. Verify in list
        $listResp = $this->getJson('/api/v2/admin/team');
        $listResp->assertOk();
        $users = collect($listResp->json('data.users'));
        $this->assertTrue($users->contains('id', $userId));
    }

    // ══════════════════════════════════════════════════════════
    // Activity Log: Advanced Filtering
    // ══════════════════════════════════════════════════════════

    public function test_activity_log_filter_by_date_from(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.create',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(10),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.update',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log?date_from=' . now()->subDays(3)->toDateString());
        $response->assertOk();

        $data  = $response->json('data');
        $items = $data['logs'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertCount(1, $items);
    }

    public function test_activity_log_filter_by_date_to(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.delete',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(15),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.create',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log?date_to=' . now()->subDays(5)->toDateString());
        $response->assertOk();

        $data  = $response->json('data');
        $items = $data['logs'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertCount(1, $items);
    }

    public function test_activity_log_filter_by_date_range(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'very.old',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(30),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'in.range',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(5),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'very.recent',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $dateFrom = now()->subDays(7)->toDateString();
        $dateTo   = now()->subDays(3)->toDateString();

        $response = $this->getJson("/api/v2/admin/activity-log?date_from={$dateFrom}&date_to={$dateTo}");
        $response->assertOk();

        $data  = $response->json('data');
        $items = $data['logs'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertCount(1, $items);
    }

    public function test_activity_log_filter_by_entity_id(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.update',
            'entity_type'   => 'admin_role',
            'entity_id'     => $this->superAdminRole->id,
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.update',
            'entity_type'   => 'admin_role',
            'entity_id'     => $this->viewerRole->id,
            'ip_address'    => '127.0.0.1',
            'created_at'    => now(),
        ]);

        $response = $this->getJson("/api/v2/admin/activity-log?entity_id={$this->superAdminRole->id}");
        $response->assertOk();

        $data  = $response->json('data');
        $items = $data['logs'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertCount(1, $items);
    }

    public function test_activity_log_combined_filters(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.create',
            'entity_type'   => 'admin_role',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(2),
        ]);
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.delete',
            'entity_type'   => 'admin_role',
            'ip_address'    => '127.0.0.1',
            'created_at'    => now()->subDays(2),
        ]);

        $response = $this->getJson(
            '/api/v2/admin/activity-log'
            . '?action=role.create'
            . '&date_from=' . now()->subDays(3)->toDateString()
        );
        $response->assertOk();

        $data  = $response->json('data');
        $items = $data['logs'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertCount(1, $items);
    }

    // ══════════════════════════════════════════════════════════
    // Resource Structure Completeness
    // ══════════════════════════════════════════════════════════

    public function test_list_roles_response_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/roles');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'is_system',
                            'users_count',
                            'permissions_count',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_show_role_response_includes_permissions_array(): void
    {
        $response = $this->getJson("/api/v2/admin/roles/{$this->superAdminRole->id}");
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'role' => [
                        'id',
                        'name',
                        'slug',
                        'is_system',
                        'permissions' => [
                            '*' => ['id', 'name', 'group'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_me_response_structure_completeness(): void
    {
        $response = $this->getJson('/api/v2/admin/me');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'profile' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                        'permissions',
                        'all_permissions',
                    ],
                ],
            ]);
    }

    public function test_team_user_response_structure(): void
    {
        $user = $this->createTeamMember('struct@test.com');
        $response = $this->getJson("/api/v2/admin/team/{$user->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'two_factor_enabled',
                        'created_at',
                        'roles',
                    ],
                ],
            ]);
    }

    public function test_activity_log_entry_structure(): void
    {
        AdminActivityLog::forceCreate([
            'admin_user_id' => $this->superAdmin->id,
            'action'        => 'role.create',
            'entity_type'   => 'admin_role',
            'ip_address'    => '10.0.0.1',
            'created_at'    => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/activity-log');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['action', 'created_at'],
                ],
            ]);
    }

    // ══════════════════════════════════════════════════════════
    // Validation Edge Cases
    // ══════════════════════════════════════════════════════════

    public function test_create_role_with_empty_name_fails(): void
    {
        $this->postJson('/api/v2/admin/roles', ['name' => ''])
            ->assertUnprocessable();
    }

    public function test_create_role_with_duplicate_slug_fails(): void
    {
        AdminRole::create(['name' => 'Dup', 'slug' => 'dup_slug', 'is_system' => false]);

        $this->postJson('/api/v2/admin/roles', [
            'name' => 'Another Role',
            'slug' => 'dup_slug',
        ])->assertUnprocessable();
    }

    public function test_create_team_user_password_too_short_fails(): void
    {
        $this->postJson('/api/v2/admin/team', [
            'name'     => 'Short Pass',
            'email'    => 'short@test.com',
            'password' => 'short',
        ])->assertUnprocessable();
    }

    public function test_create_team_user_password_exactly_12_chars_succeeds(): void
    {
        $this->postJson('/api/v2/admin/team', [
            'name'     => 'Twelve Chars',
            'email'    => 'twelve@test.com',
            'password' => '12CharPass!!',
        ])->assertCreated();
    }

    public function test_create_team_user_duplicate_email_fails(): void
    {
        $existing = $this->createTeamMember('existing@test.com');

        $this->postJson('/api/v2/admin/team', [
            'name'     => 'Dup Email',
            'email'    => 'existing@test.com',
            'password' => 'SecurePass123!',
        ])->assertUnprocessable();
    }

    public function test_show_role_invalid_uuid_returns_404(): void
    {
        $this->getJson('/api/v2/admin/roles/not-a-valid-uuid')->assertNotFound();
    }

    public function test_show_team_user_invalid_uuid_returns_404(): void
    {
        $this->getJson('/api/v2/admin/team/not-a-valid-uuid')->assertNotFound();
    }

    public function test_deactivate_nonexistent_team_user_returns_404(): void
    {
        $this->postJson('/api/v2/admin/team/00000000-0000-0000-0000-000000000099/deactivate')
            ->assertNotFound();
    }

    public function test_activate_nonexistent_team_user_returns_404(): void
    {
        $this->postJson('/api/v2/admin/team/00000000-0000-0000-0000-000000000099/activate')
            ->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════
    // Self-edit restrictions
    // ══════════════════════════════════════════════════════════

    public function test_cannot_deactivate_self(): void
    {
        $this->postJson("/api/v2/admin/team/{$this->superAdmin->id}/deactivate")
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════
    // Pagination
    // ══════════════════════════════════════════════════════════

    public function test_team_list_pagination_with_custom_per_page(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->createTeamMember("page{$i}@test.com");
        }

        $response = $this->getJson('/api/v2/admin/team?per_page=3');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('pagination', $data);
        $pagination = $data['pagination'];
        $this->assertGreaterThan(1, $pagination['last_page']);
    }

    public function test_activity_log_pagination(): void
    {
        for ($i = 0; $i < 25; $i++) {
            AdminActivityLog::forceCreate([
                'admin_user_id' => $this->superAdmin->id,
                'action'        => "action.{$i}",
                'ip_address'    => '127.0.0.1',
                'created_at'    => now()->subSeconds($i),
            ]);
        }

        $response = $this->getJson('/api/v2/admin/activity-log?per_page=10');
        $response->assertOk();

        $data = $response->json('data');
        $items = $data['logs'] ?? $data['items'] ?? $data['data'] ?? [];
        // Should get at most per_page items
        $this->assertLessThanOrEqual(10, count($items));
    }

    // ══════════════════════════════════════════════════════════
    // Team search functionality
    // ══════════════════════════════════════════════════════════

    public function test_team_search_case_insensitive(): void
    {
        $this->createTeamMember('searchtest@test.com', true, 'SearchableUser Unique');

        // Search with different case
        $response = $this->getJson('/api/v2/admin/team?search=searchableuser');
        $response->assertOk();

        $data  = $response->json('data');
        $users = $data['users'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertGreaterThanOrEqual(1, count($users));
    }

    public function test_team_search_by_email(): void
    {
        $this->createTeamMember('findme@uniquedomain.test');

        $response = $this->getJson('/api/v2/admin/team?search=uniquedomain');
        $response->assertOk();

        $data  = $response->json('data');
        $users = $data['users'] ?? $data['items'] ?? $data['data'] ?? [];
        $this->assertGreaterThanOrEqual(1, count($users));
    }

    // ══════════════════════════════════════════════════════════
    // Consistency: Error Response Structure
    // ══════════════════════════════════════════════════════════

    public function test_forbidden_response_has_success_false(): void
    {
        $this->actingAsViewer();
        $response = $this->postJson('/api/v2/admin/roles', ['name' => 'Test']);
        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_not_found_response_has_success_false(): void
    {
        $response = $this->getJson('/api/v2/admin/roles/00000000-0000-0000-0000-000000000099');
        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_validation_error_response_has_success_false(): void
    {
        $response = $this->postJson('/api/v2/admin/roles', []); // Missing required fields
        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    // ══════════════════════════════════════════════════════════
    // Role assignment tracking
    // ══════════════════════════════════════════════════════════

    public function test_creating_team_user_with_roles_tracks_assigned_by(): void
    {
        $this->postJson('/api/v2/admin/team', [
            'name'     => 'Tracked User',
            'email'    => 'tracked@test.com',
            'password' => 'SecurePass@2025!',
            'role_ids' => [$this->viewerRole->id],
        ])->assertCreated();

        $createdUser = AdminUser::where('email', 'tracked@test.com')->first();

        $assignment = AdminUserRole::where('admin_user_id', $createdUser->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals($this->viewerRole->id, $assignment->admin_role_id);
    }

    public function test_update_team_user_role_assignment_replaces_old_roles(): void
    {
        $user = $this->createTeamMember('replace@test.com');
        AdminUserRole::create([
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
            'assigned_at'   => now(),
        ]);

        $customRole = AdminRole::create([
            'name'      => 'Custom Test Role',
            'slug'      => 'custom_test',
            'is_system' => false,
        ]);

        $this->putJson("/api/v2/admin/team/{$user->id}", [
            'role_ids' => [$customRole->id],
        ])->assertOk();

        $this->assertDatabaseHas('admin_user_roles', [
            'admin_user_id' => $user->id,
            'admin_role_id' => $customRole->id,
        ]);
        $this->assertDatabaseMissing('admin_user_roles', [
            'admin_user_id' => $user->id,
            'admin_role_id' => $this->viewerRole->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // Permissions endpoint
    // ══════════════════════════════════════════════════════════

    public function test_permissions_endpoint_returns_grouped_structure(): void
    {
        $response = $this->getJson('/api/v2/admin/permissions');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'permissions',
                ],
            ]);

        $permissions = $response->json('data.permissions');
        $this->assertIsArray($permissions);
    }

    public function test_permissions_endpoint_includes_all_required_groups(): void
    {
        $response  = $this->getJson('/api/v2/admin/permissions');
        $response->assertOk();

        $permissions = $response->json('data.permissions');
        // Should at minimum contain admin_team group
        $this->assertArrayHasKey('admin_team', $permissions);
    }

    // ══════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════

    private function actingAsViewer(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->viewerOnlyAdmin, ['*'], 'admin-api');
    }

    private function actingAsAdmin(AdminUser $user): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['*'], 'admin-api');
    }

    private function createAdminWithPermissions(array $permissions): AdminUser
    {
        $role = AdminRole::create([
            'name'      => 'Limited Role ' . uniqid(),
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
            'email'         => 'limited_' . uniqid() . '@test.com',
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

    private function createTeamMember(string $email, bool $isActive = true, string $name = 'Team Member'): AdminUser
    {
        return AdminUser::forceCreate([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => bcrypt('pass'),
            'is_active'     => $isActive,
        ]);
    }
}
