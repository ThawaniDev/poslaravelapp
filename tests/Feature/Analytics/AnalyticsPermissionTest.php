<?php

namespace Tests\Feature\Analytics;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BypassPermissionMiddleware;
use Tests\TestCase;

/**
 * Analytics Permission Enforcement Tests
 *
 * Verifies that analytics API endpoints are properly guarded:
 *  - 401 for unauthenticated requests
 *  - 403 for admins lacking required permissions
 *  - 200 for admins with analytics.view (super-permission)
 *  - 200 for admins with a specific sub-permission (e.g. analytics.revenue)
 *  - 403 for export endpoints when lacking analytics.export
 *
 * NOTE: These tests override the base BypassPermissionMiddleware with the
 * real CheckPermission middleware so that enforcement is actually verified.
 */
class AnalyticsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Override the test bypass with the real permission middleware
        // so that 403 responses are actually tested.
        $router = app('router');
        $router->aliasMiddleware('permission', CheckPermission::class);
    }

    protected function tearDown(): void
    {
        // Restore the bypass middleware so subsequent test classes are unaffected.
        $router = app('router');
        $router->aliasMiddleware('permission', BypassPermissionMiddleware::class);

        parent::tearDown();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(bool $superAdmin = false, array $permissionNames = []): AdminUser
    {
        $admin = AdminUser::forceCreate([
            'name'          => 'Test Admin',
            'email'         => 'admin_' . uniqid() . '@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        if ($superAdmin) {
            $role = AdminRole::forceCreate([
                'name'      => 'Super Admin',
                'slug'      => 'super_admin',
                'is_system' => true,
            ]);
            $admin->roles()->attach($role->id);
        } elseif (!empty($permissionNames)) {
            $role = AdminRole::forceCreate([
                'name'      => 'Analytics Role ' . uniqid(),
                'slug'      => 'analytics_role_' . uniqid(),
                'is_system' => false,
            ]);

            foreach ($permissionNames as $name) {
                $permission = AdminPermission::firstOrCreate(
                    ['name' => $name],
                    ['group' => 'analytics', 'description' => "Permission: {$name}"]
                );
                $role->permissions()->attach($permission->id);
            }

            $admin->roles()->attach($role->id);
        }

        // Clear permission cache so the test picks up fresh assignments
        Cache::forget("admin_user:{$admin->id}:permissions");
        Cache::forget("admin_user:{$admin->id}:is_super_admin");

        return $admin;
    }

    private function actAs(AdminUser $admin): void
    {
        Sanctum::actingAs($admin, ['*'], 'admin-api');
        // Reset cached permissions on the model instance
        $admin->cachedPermissions  = null;
        $admin->cachedIsSuperAdmin = null;
    }

    // ═══════════════════════════════════════════════════════════
    // AUTHENTICATION GUARD
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_request_to_dashboard_returns_401(): void
    {
        $this->getJson('/api/v2/admin/analytics/dashboard')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_request_to_revenue_returns_401(): void
    {
        $this->getJson('/api/v2/admin/analytics/revenue')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_request_to_export_returns_401(): void
    {
        $this->postJson('/api/v2/admin/analytics/export/revenue')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // NO PERMISSIONS → 403
    // ═══════════════════════════════════════════════════════════

    public function test_admin_without_any_analytics_permission_cannot_access_dashboard(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/dashboard')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_revenue(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/revenue')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_subscriptions(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/subscriptions')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_stores(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/stores')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_features(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/features')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_support(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/support')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_system_health(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/system-health')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_notifications(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/notifications')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_daily_stats(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/daily-stats')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_plan_stats(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/plan-stats')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_feature_stats(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/feature-stats')
            ->assertForbidden();
    }

    public function test_admin_without_analytics_view_cannot_access_store_health(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/store-health')
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // analytics.view → ACCESS GRANTED
    // ═══════════════════════════════════════════════════════════

    public function test_admin_with_analytics_view_can_access_dashboard(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.view']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_with_analytics_view_can_access_revenue(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.view']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/revenue')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_with_analytics_view_can_access_all_dashboards(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.view']);
        $this->actAs($admin);

        $endpoints = [
            '/api/v2/admin/analytics/dashboard',
            '/api/v2/admin/analytics/revenue',
            '/api/v2/admin/analytics/subscriptions',
            '/api/v2/admin/analytics/stores',
            '/api/v2/admin/analytics/features',
            '/api/v2/admin/analytics/support',
            '/api/v2/admin/analytics/system-health',
            '/api/v2/admin/analytics/notifications',
            '/api/v2/admin/analytics/daily-stats',
            '/api/v2/admin/analytics/plan-stats',
            '/api/v2/admin/analytics/feature-stats',
            '/api/v2/admin/analytics/store-health',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk(">>>> FAILED: {$endpoint}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Sub-permissions grant scoped access
    // ═══════════════════════════════════════════════════════════

    public function test_admin_with_analytics_revenue_can_access_revenue_dashboard(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.revenue']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/revenue')
            ->assertOk();
    }

    public function test_admin_with_analytics_revenue_cannot_access_subscriptions(): void
    {
        // analytics.revenue gives access only to revenue; subscriptions requires analytics.view or analytics.subscriptions
        $admin = $this->makeAdmin(false, ['analytics.revenue']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/subscriptions')
            ->assertForbidden();
    }

    public function test_admin_with_analytics_subscriptions_can_access_subscriptions(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.subscriptions']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/subscriptions')
            ->assertOk();
    }

    public function test_admin_with_analytics_stores_can_access_stores(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.stores']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/stores')
            ->assertOk();
    }

    public function test_admin_with_analytics_features_can_access_features(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.features']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/features')
            ->assertOk();
    }

    public function test_admin_with_analytics_support_can_access_support(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.support']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/support')
            ->assertOk();
    }

    public function test_admin_with_analytics_notifications_can_access_notifications(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.notifications']);
        $this->actAs($admin);

        $this->getJson('/api/v2/admin/analytics/notifications')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // EXPORT PERMISSIONS
    // ═══════════════════════════════════════════════════════════

    public function test_admin_without_export_permission_cannot_export_revenue(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.view']); // view but not export
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/revenue', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to'   => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_admin_without_export_permission_cannot_export_subscriptions(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.view']);
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/subscriptions', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to'   => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_admin_without_export_permission_cannot_export_stores(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.view']);
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/stores')
            ->assertForbidden();
    }

    public function test_admin_with_analytics_export_can_export_revenue(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.export']);
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/revenue', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to'   => now()->toDateString(),
        ])->assertOk();
    }

    public function test_admin_with_analytics_export_can_export_subscriptions(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.export']);
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/subscriptions', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to'   => now()->toDateString(),
        ])->assertOk();
    }

    public function test_admin_with_analytics_export_can_export_stores(): void
    {
        $admin = $this->makeAdmin(false, ['analytics.export']);
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/stores')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // SUPER ADMIN BYPASSES ALL
    // ═══════════════════════════════════════════════════════════

    public function test_super_admin_can_access_all_analytics_endpoints(): void
    {
        $admin = $this->makeAdmin(true);
        $this->actAs($admin);

        $endpoints = [
            '/api/v2/admin/analytics/dashboard',
            '/api/v2/admin/analytics/revenue',
            '/api/v2/admin/analytics/subscriptions',
            '/api/v2/admin/analytics/stores',
            '/api/v2/admin/analytics/features',
            '/api/v2/admin/analytics/support',
            '/api/v2/admin/analytics/system-health',
            '/api/v2/admin/analytics/notifications',
            '/api/v2/admin/analytics/daily-stats',
            '/api/v2/admin/analytics/plan-stats',
            '/api/v2/admin/analytics/feature-stats',
            '/api/v2/admin/analytics/store-health',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk(">>>> FAILED: {$endpoint}");
        }
    }

    public function test_super_admin_can_export_all(): void
    {
        $admin = $this->makeAdmin(true);
        $this->actAs($admin);

        $this->postJson('/api/v2/admin/analytics/export/revenue', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to'   => now()->toDateString(),
        ])->assertOk();

        $this->postJson('/api/v2/admin/analytics/export/subscriptions', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to'   => now()->toDateString(),
        ])->assertOk();

        $this->postJson('/api/v2/admin/analytics/export/stores')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // FORBIDDEN RESPONSE SHAPE
    // ═══════════════════════════════════════════════════════════

    public function test_forbidden_response_includes_required_permissions(): void
    {
        $admin = $this->makeAdmin(false, []);
        $this->actAs($admin);

        $response = $this->getJson('/api/v2/admin/analytics/dashboard');

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'required_permissions']);

        $this->assertContains('analytics.view', $response->json('required_permissions'));
    }
}
