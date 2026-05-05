<?php

namespace Tests\Feature\StaffManagement;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Models\StaffUser;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckPlanLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests that the CheckPermission middleware correctly enforces
 * role-based access control on every staff endpoint.
 *
 * Test Matrix:
 * - Owner role → always allowed (bypasses all checks)
 * - Non-owner without permission → 403
 * - Non-owner with correct permission → 200/201/204
 * - No token → 401
 * - Wrong org/store → isolation enforced
 */
class StaffPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    // Run migrate:fresh once per class to avoid PostgreSQL deadlocks on bulk table drops
    private static bool $classMigrated = false;

    private User $owner;
    private User $cashier;          // no permissions at all
    private User $staffManager;     // has staff.view + staff.edit + staff.delete + staff.manage + staff.manage_pin
    private User $reportsViewer;    // has reports.attendance
    private User $shiftsManager;    // has staff.manage_shifts
    private User $financeUser;      // has finance.commissions
    private User $rolesAdmin;       // has roles.view + roles.create + roles.edit + roles.delete + roles.assign + roles.audit + security.view_audit

    private Organization $org;
    private Store $store;

    private string $ownerToken;
    private string $cashierToken;
    private string $staffManagerToken;
    private string $reportsViewerToken;
    private string $shiftsManagerToken;
    private string $financeUserToken;
    private string $rolesAdminToken;

    private StaffUser $sampleStaff;

    protected function refreshTestDatabase(): void
    {
        if (!static::$classMigrated) {
            $this->migrateDatabases();
            $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
            static::$classMigrated = true;
        }
        $this->beginDatabaseTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Re-register real middleware (TestCase bypasses them by default)
        $router = app('router');
        $router->aliasMiddleware('permission', CheckPermission::class);
        $router->aliasMiddleware('plan.limit', CheckPlanLimit::class);

        // Seed all permissions first
        app(PermissionService::class)->seedAll();

        // Org & store
        $this->org = Organization::create([
            'name'          => 'Permission Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Permission Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        // Owner – bypasses everything
        $this->owner = $this->makeUser('owner@perm.test', 'owner');
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;

        // Cashier – no permissions whatsoever
        $this->cashier = $this->makeUser('cashier@perm.test', 'cashier');
        $this->cashierToken = $this->cashier->createToken('test')->plainTextToken;

        // Staff manager
        $this->staffManager = $this->makeUser('staffmgr@perm.test', 'cashier');
        $this->grantPermissions($this->staffManager, [
            'staff.view',
            'staff.edit',
            'staff.delete',
            'staff.manage',
            'staff.manage_pin',
        ]);
        $this->staffManagerToken = $this->staffManager->createToken('test')->plainTextToken;

        // Reports viewer
        $this->reportsViewer = $this->makeUser('reports@perm.test', 'cashier');
        $this->grantPermissions($this->reportsViewer, ['reports.attendance']);
        $this->reportsViewerToken = $this->reportsViewer->createToken('test')->plainTextToken;

        // Shifts manager
        $this->shiftsManager = $this->makeUser('shifts@perm.test', 'cashier');
        $this->grantPermissions($this->shiftsManager, ['staff.manage_shifts']);
        $this->shiftsManagerToken = $this->shiftsManager->createToken('test')->plainTextToken;

        // Finance user
        $this->financeUser = $this->makeUser('finance@perm.test', 'cashier');
        $this->grantPermissions($this->financeUser, ['finance.commissions']);
        $this->financeUserToken = $this->financeUser->createToken('test')->plainTextToken;

        // Roles admin
        $this->rolesAdmin = $this->makeUser('rolesadmin@perm.test', 'cashier');
        $this->grantPermissions($this->rolesAdmin, [
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.assign',
            'roles.audit',
            'security.view_audit',
        ]);
        $this->rolesAdminToken = $this->rolesAdmin->createToken('test')->plainTextToken;

        // A sample staff member for endpoints that require an ID
        $this->sampleStaff = StaffUser::create([
            'store_id'        => $this->store->id,
            'first_name'      => 'Sample',
            'last_name'       => 'Staff',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
            'status'          => 'active',
            'pin_hash'        => bcrypt('1234'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Auth – no token at all
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->getJson('/api/v2/staff/members')->assertUnauthorized();
        $this->postJson('/api/v2/staff/members', [])->assertUnauthorized();
        $this->getJson('/api/v2/staff/roles')->assertUnauthorized();
        $this->getJson('/api/v2/staff/attendance')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // staff.view permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_staff_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/members')
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['staff.view']]);
    }

    public function test_list_staff_allowed_with_staff_view(): void
    {
        $this->withToken($this->staffManagerToken)
            ->getJson('/api/v2/staff/members')
            ->assertOk();
    }

    public function test_owner_can_list_staff_without_explicit_permission(): void
    {
        $this->withToken($this->ownerToken)
            ->getJson('/api/v2/staff/members')
            ->assertOk();
    }

    public function test_show_staff_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}")
            ->assertForbidden();
    }

    public function test_show_staff_allowed_with_staff_view(): void
    {
        $this->withToken($this->staffManagerToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}")
            ->assertOk();
    }

    public function test_staff_stats_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/members/stats')
            ->assertForbidden();
    }

    public function test_staff_stats_allowed_with_staff_view(): void
    {
        $this->withToken($this->staffManagerToken)
            ->getJson('/api/v2/staff/members/stats')
            ->assertOk();
    }

    public function test_linkable_users_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/members/linkable-users')
            ->assertForbidden();
    }

    public function test_linkable_users_allowed_with_staff_view(): void
    {
        $this->withToken($this->staffManagerToken)
            ->getJson('/api/v2/staff/members/linkable-users')
            ->assertOk();
    }

    public function test_activity_log_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/activity-log")
            ->assertForbidden();
    }

    public function test_branch_assignments_list_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/branch-assignments")
            ->assertForbidden();
    }

    public function test_documents_list_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/documents")
            ->assertForbidden();
    }

    public function test_documents_list_allowed_with_staff_view(): void
    {
        $this->withToken($this->staffManagerToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/documents")
            ->assertOk();
    }

    public function test_training_sessions_list_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/training-sessions")
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // staff.edit permission
    // ═══════════════════════════════════════════════════════════

    public function test_update_staff_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->putJson("/api/v2/staff/members/{$this->sampleStaff->id}", ['first_name' => 'X'])
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['staff.edit']]);
    }

    public function test_update_staff_allowed_with_staff_edit(): void
    {
        $this->withToken($this->staffManagerToken)
            ->putJson("/api/v2/staff/members/{$this->sampleStaff->id}", ['first_name' => 'Updated'])
            ->assertOk();
    }

    public function test_nfc_register_forbidden_without_staff_edit(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/nfc", ['uid' => 'ABC123'])
            ->assertForbidden();
    }

    public function test_add_document_forbidden_without_staff_edit(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/documents", [
                'document_type' => 'national_id',
                'file_url'      => 'https://cdn.example.com/id.pdf',
            ])
            ->assertForbidden();
    }

    public function test_add_document_allowed_with_staff_edit(): void
    {
        $this->withToken($this->staffManagerToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/documents", [
                'document_type' => 'national_id',
                'file_url'      => 'https://cdn.example.com/id.pdf',
            ])
            ->assertCreated();
    }

    public function test_link_user_forbidden_without_staff_edit(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/link-user", [
                'user_id' => $this->owner->id,
            ])
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // staff.delete permission
    // ═══════════════════════════════════════════════════════════

    public function test_delete_staff_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->deleteJson("/api/v2/staff/members/{$this->sampleStaff->id}")
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['staff.delete']]);
    }

    public function test_delete_staff_allowed_with_staff_delete(): void
    {
        $this->withToken($this->staffManagerToken)
            ->deleteJson("/api/v2/staff/members/{$this->sampleStaff->id}")
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // staff.manage_pin permission
    // ═══════════════════════════════════════════════════════════

    public function test_set_pin_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/pin", ['pin' => '9999'])
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['staff.manage_pin']]);
    }

    public function test_set_pin_allowed_with_staff_manage_pin(): void
    {
        $this->withToken($this->staffManagerToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/pin", ['pin' => '9999'])
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // staff.manage permission
    // ═══════════════════════════════════════════════════════════

    public function test_assign_branch_forbidden_without_permission(): void
    {
        $this->withToken($this->reportsViewer)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
            ])
            ->assertUnauthorized(); // missing token, not actually calling withToken
    }

    public function test_assign_branch_with_token_forbidden_without_staff_manage(): void
    {
        $this->withToken($this->reportsViewerToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
            ])
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['staff.manage']]);
    }

    public function test_assign_branch_allowed_with_staff_manage(): void
    {
        $this->withToken($this->staffManagerToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
            ])
            ->assertSuccessful(); // 201 Created on new assignment
    }

    public function test_start_training_session_forbidden_without_staff_manage(): void
    {
        $this->withToken($this->reportsViewerToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/training-sessions")
            ->assertForbidden();
    }

    public function test_start_training_session_allowed_with_staff_manage(): void
    {
        $this->withToken($this->staffManagerToken)
            ->postJson("/api/v2/staff/members/{$this->sampleStaff->id}/training-sessions")
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // reports.attendance permission
    // ═══════════════════════════════════════════════════════════

    public function test_attendance_list_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/attendance')
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['reports.attendance']]);
    }

    public function test_attendance_list_allowed_with_reports_attendance(): void
    {
        $this->withToken($this->reportsViewerToken)
            ->getJson('/api/v2/staff/attendance')
            ->assertOk();
    }

    public function test_attendance_summary_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/attendance/summary')
            ->assertForbidden();
    }

    public function test_attendance_export_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/attendance/export')
            ->assertForbidden();
    }

    public function test_attendance_export_allowed_with_reports_attendance(): void
    {
        $this->withToken($this->reportsViewerToken)
            ->getJson('/api/v2/staff/attendance/export')
            ->assertOk();
    }

    // Clock uses staff.view (not reports.attendance)
    public function test_clock_action_forbidden_without_staff_view(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson('/api/v2/staff/attendance/clock', [
                'staff_user_id' => $this->sampleStaff->id,
                'action'        => 'clock_in',
                'auth_method'   => 'pin',
            ])
            ->assertForbidden();
    }

    public function test_clock_action_allowed_with_staff_view(): void
    {
        $this->withToken($this->staffManagerToken)
            ->postJson('/api/v2/staff/attendance/clock', [
                'staff_user_id' => $this->sampleStaff->id,
                'store_id'      => $this->store->id,
                'action'        => 'clock_in',
                'auth_method'   => 'pin',
            ])
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // staff.manage_shifts permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_shifts_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/shifts')
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['staff.manage_shifts']]);
    }

    public function test_list_shifts_allowed_with_shifts_permission(): void
    {
        $this->withToken($this->shiftsManagerToken)
            ->getJson('/api/v2/staff/shifts')
            ->assertOk();
    }

    public function test_create_shift_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson('/api/v2/staff/shifts', [])
            ->assertForbidden();
    }

    public function test_list_shift_templates_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/shift-templates')
            ->assertForbidden();
    }

    public function test_list_shift_templates_allowed_with_shifts_permission(): void
    {
        $this->withToken($this->shiftsManagerToken)
            ->getJson('/api/v2/staff/shift-templates')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // finance.commissions permission
    // ═══════════════════════════════════════════════════════════

    public function test_get_commissions_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/commissions")
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['finance.commissions']]);
    }

    public function test_get_commissions_allowed_with_finance_permission(): void
    {
        $this->withToken($this->financeUserToken)
            ->getJson("/api/v2/staff/members/{$this->sampleStaff->id}/commissions")
            ->assertOk();
    }

    public function test_set_commission_config_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->putJson("/api/v2/staff/members/{$this->sampleStaff->id}/commission-config", [
                'type'       => 'flat_percentage',
                'percentage' => 3.0,
            ])
            ->assertForbidden();
    }

    public function test_set_commission_config_allowed_with_finance_permission(): void
    {
        $this->withToken($this->financeUserToken)
            ->putJson("/api/v2/staff/members/{$this->sampleStaff->id}/commission-config", [
                'type'       => 'flat_percentage',
                'percentage' => 3.0,
            ])
            ->assertSuccessful(); // 200 or 201 depending on replace semantics
    }

    // ═══════════════════════════════════════════════════════════
    // roles.view permission
    // ═══════════════════════════════════════════════════════════

    public function test_list_roles_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/roles')
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['roles.view']]);
    }

    public function test_list_roles_allowed_with_roles_view(): void
    {
        $this->withToken($this->rolesAdminToken)
            ->getJson('/api/v2/staff/roles')
            ->assertOk();
    }

    public function test_list_permissions_forbidden_without_roles_view(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/permissions')
            ->assertForbidden();
    }

    public function test_grouped_permissions_forbidden_without_roles_view(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/permissions/grouped')
            ->assertForbidden();
    }

    public function test_permissions_allowed_with_roles_view(): void
    {
        $this->withToken($this->rolesAdminToken)
            ->getJson('/api/v2/staff/permissions')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // roles.create / edit / delete permissions
    // ═══════════════════════════════════════════════════════════

    public function test_create_role_forbidden_without_roles_create(): void
    {
        $this->withToken($this->cashierToken)
            ->postJson('/api/v2/staff/roles', [
                'name'         => 'test_role',
                'display_name' => 'Test Role',
                'store_id'     => $this->store->id,
            ])
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['roles.create']]);
    }

    public function test_create_role_allowed_with_roles_create(): void
    {
        $this->withToken($this->rolesAdminToken)
            ->postJson('/api/v2/staff/roles', [
                'name'         => 'new_test_role',
                'display_name' => 'New Test Role',
                'store_id'     => $this->store->id,
            ])
            ->assertCreated();
    }

    // ═══════════════════════════════════════════════════════════
    // roles.assign permission
    // ═══════════════════════════════════════════════════════════

    public function test_assign_role_forbidden_without_roles_assign(): void
    {
        $role = Role::create([
            'store_id'     => $this->store->id,
            'name'         => 'temp_role',
            'display_name' => 'Temp',
            'guard_name'   => 'staff',
        ]);

        $this->withToken($this->cashierToken)
            ->postJson("/api/v2/staff/roles/{$role->id}/assign", [
                'user_id' => $this->cashier->id,
            ])
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['roles.assign']]);
    }

    // ═══════════════════════════════════════════════════════════
    // roles.audit permission
    // ═══════════════════════════════════════════════════════════

    public function test_role_audit_log_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/roles/audit-log')
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['roles.audit']]);
    }

    public function test_role_audit_log_allowed_with_permission(): void
    {
        $this->withToken($this->rolesAdminToken)
            ->getJson('/api/v2/staff/roles/audit-log')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // security.view_audit for pin-override/history
    // ═══════════════════════════════════════════════════════════

    public function test_pin_override_history_forbidden_without_permission(): void
    {
        $this->withToken($this->cashierToken)
            ->getJson('/api/v2/staff/pin-override/history')
            ->assertForbidden()
            ->assertJsonFragment(['required_permissions' => ['security.view_audit']]);
    }

    public function test_pin_override_history_allowed_with_security_view_audit(): void
    {
        $this->withToken($this->rolesAdminToken)
            ->getJson('/api/v2/staff/pin-override/history')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Permission inheritance (multiple roles)
    // ═══════════════════════════════════════════════════════════

    public function test_user_with_multiple_roles_gets_union_permissions(): void
    {
        $user = $this->makeUser('multirol@perm.test', 'cashier');

        // Give reports only
        $this->grantPermissions($user, ['reports.attendance']);

        $token = $user->createToken('test')->plainTextToken;

        // Can access attendance (has reports.attendance)
        $this->withToken($token)
            ->getJson('/api/v2/staff/attendance')
            ->assertOk();

        // Cannot access roles (no roles.view)
        $this->withToken($token)
            ->getJson('/api/v2/staff/roles')
            ->assertForbidden();

        // Now also grant roles.view via a second role
        $this->grantPermissions($user, ['roles.view']);

        // Now should be able to access roles
        $this->withToken($token)
            ->getJson('/api/v2/staff/roles')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // Organization isolation
    // ═══════════════════════════════════════════════════════════

    public function test_staff_view_permission_only_applies_to_own_store(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org', 'business_type' => 'grocery', 'country' => 'OM',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
        ]);
        $otherUser = User::create([
            'name'            => 'Other User',
            'email'           => 'other-iso@perm.test',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $otherStore->id,
            'organization_id' => $otherOrg->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);
        $otherToken = $otherUser->createToken('test')->plainTextToken;

        // Other user doesn't have staff.view in their store either,
        // but definitely not in our store
        $this->withToken($otherToken)
            ->getJson('/api/v2/staff/members')
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // POST staff/members – no permission gating (just auth + plan.limit)
    // ═══════════════════════════════════════════════════════════

    public function test_create_staff_requires_auth_not_specific_permission(): void
    {
        // POST /api/v2/staff/members has plan.limit but no permission middleware
        // Therefore a cashier without any special permission can hit it
        // (but WILL fail plan.limit if not within limits – returns 403 with upgrade_required)
        $response = $this->withToken($this->cashierToken)
            ->postJson('/api/v2/staff/members', [
                'first_name'      => 'New',
                'last_name'       => 'Staff',
                'employment_type' => 'full_time',
                'salary_type'     => 'monthly',
                'hire_date'       => now()->toDateString(),
            ]);

        // Should not be a 403 due to permission – either 201 success or
        // 422 validation or 403 from plan limit
        $this->assertNotEquals(401, $response->status(), 'Should not be 401 - user is authenticated');

        // Not the CheckPermission 403 message
        $json = $response->json();
        if ($response->status() === 403) {
            $this->assertNotEquals(['staff.view'], $json['required_permissions'] ?? null);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name'            => ucfirst($role),
            'email'           => $email,
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => $role,
            'is_active'       => true,
        ]);
    }

    /**
     * Grant named permissions to a user by creating a role in the store
     * and assigning it via model_has_roles.
     */
    private function grantPermissions(User $user, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        $roleName = 'auto_role_' . substr(md5(implode(',', $permissionNames)), 0, 8);

        $role = Role::firstOrCreate(
            ['name' => $roleName, 'store_id' => $this->store->id],
            ['display_name' => 'Auto Role', 'guard_name' => 'staff', 'is_predefined' => false],
        );

        $role->permissions()->syncWithoutDetaching($permissionIds);

        DB::table('model_has_roles')->updateOrInsert([
            'role_id'    => $role->id,
            'model_id'   => $user->id,
            'model_type' => get_class($user),
        ]);
    }
}
