<?php

namespace Tests\Feature\Shared;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\Permission;
use App\Domain\StaffManagement\Models\Role;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BypassPermissionMiddleware;
use Tests\TestCase;

/**
 * Accessibility Permission Enforcement Tests
 *
 * Verifies that accessibility endpoints are properly guarded:
 *  - 401 for unauthenticated requests
 *  - 403 for authenticated users WITHOUT accessibility.manage permission
 *  - 200 for authenticated users WITH accessibility.manage permission
 *  - 200 for store owners (bypass all permissions)
 *
 * These tests override the base BypassPermissionMiddleware with the real
 * CheckPermission middleware so enforcement is actually verified.
 */
class AccessibilityPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $userNoPerms;
    private User $userWithPerms;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable real permission middleware for this test class
        app('router')->aliasMiddleware('permission', CheckPermission::class);

        // Seed all permissions so accessibility.manage exists
        app(PermissionService::class)->seedAll();

        $this->org = Organization::create([
            'name'          => 'Perm Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Perm Test Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->owner        = $this->makeUser('owner@access-perm.test',    'owner');
        $this->userNoPerms  = $this->makeUser('noperms@access-perm.test',  'cashier');
        $this->userWithPerms = $this->makeUser('withperms@access-perm.test', 'cashier');
        $this->grantPermissions($this->userWithPerms, ['accessibility.manage']);
    }

    protected function tearDown(): void
    {
        // Restore bypass middleware so other tests are unaffected
        app('router')->aliasMiddleware('permission', BypassPermissionMiddleware::class);
        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function token(User $user): string
    {
        return $user->createToken('perm-test')->plainTextToken;
    }

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

    private function grantPermissions(User $user, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');
        $slug          = 'acc_perm_' . substr(md5(implode(',', $permissionNames)), 0, 8);
        $role          = Role::firstOrCreate(
            ['name' => $slug, 'store_id' => $this->store->id],
            ['display_name' => 'Auto Role', 'guard_name' => 'staff', 'is_predefined' => false],
        );
        $role->permissions()->syncWithoutDetaching($permissionIds);
        DB::table('model_has_roles')->updateOrInsert([
            'role_id'    => $role->id,
            'model_id'   => $user->id,
            'model_type' => get_class($user),
        ]);
    }

    // ═══════════════ Unauthenticated — 401 ═══════════════

    public function test_unauthenticated_cannot_get_preferences(): void
    {
        $this->getJson('/api/v2/accessibility/preferences')->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_update_preferences(): void
    {
        $this->putJson('/api/v2/accessibility/preferences', [])->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_reset_preferences(): void
    {
        $this->deleteJson('/api/v2/accessibility/preferences')->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_get_shortcuts(): void
    {
        $this->getJson('/api/v2/accessibility/shortcuts')->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_update_shortcuts(): void
    {
        $this->putJson('/api/v2/accessibility/shortcuts', [])->assertUnauthorized();
    }

    // ═══════════════ Missing permission — 403 ═══════════════

    public function test_user_without_accessibility_manage_cannot_get_preferences(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/accessibility/preferences')
            ->assertForbidden();
    }

    public function test_user_without_accessibility_manage_cannot_update_preferences(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->putJson('/api/v2/accessibility/preferences', ['font_scale' => 1.2])
            ->assertForbidden();
    }

    public function test_user_without_accessibility_manage_cannot_reset_preferences(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->deleteJson('/api/v2/accessibility/preferences')
            ->assertForbidden();
    }

    public function test_user_without_accessibility_manage_cannot_get_shortcuts(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->getJson('/api/v2/accessibility/shortcuts')
            ->assertForbidden();
    }

    public function test_user_without_accessibility_manage_cannot_update_shortcuts(): void
    {
        $this->withToken($this->token($this->userNoPerms))
            ->putJson('/api/v2/accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3']])
            ->assertForbidden();
    }

    // ═══════════════ With permission — 200 ═══════════════

    public function test_user_with_accessibility_manage_can_get_preferences(): void
    {
        $this->withToken($this->token($this->userWithPerms))
            ->getJson('/api/v2/accessibility/preferences')
            ->assertOk();
    }

    public function test_user_with_accessibility_manage_can_update_preferences(): void
    {
        $this->withToken($this->token($this->userWithPerms))
            ->putJson('/api/v2/accessibility/preferences', ['font_scale' => 1.2])
            ->assertOk();
    }

    public function test_user_with_accessibility_manage_can_reset_preferences(): void
    {
        $this->withToken($this->token($this->userWithPerms))
            ->deleteJson('/api/v2/accessibility/preferences')
            ->assertOk();
    }

    public function test_user_with_accessibility_manage_can_get_shortcuts(): void
    {
        $this->withToken($this->token($this->userWithPerms))
            ->getJson('/api/v2/accessibility/shortcuts')
            ->assertOk();
    }

    public function test_user_with_accessibility_manage_can_update_shortcuts(): void
    {
        $this->withToken($this->token($this->userWithPerms))
            ->putJson('/api/v2/accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3']])
            ->assertOk();
    }

    // ═══════════════ Owner bypass — 200 ═══════════════

    public function test_owner_can_get_preferences_without_explicit_permission(): void
    {
        $this->withToken($this->token($this->owner))
            ->getJson('/api/v2/accessibility/preferences')
            ->assertOk();
    }

    public function test_owner_can_update_preferences_without_explicit_permission(): void
    {
        $this->withToken($this->token($this->owner))
            ->putJson('/api/v2/accessibility/preferences', ['font_scale' => 1.2])
            ->assertOk();
    }

    public function test_owner_can_reset_preferences_without_explicit_permission(): void
    {
        $this->withToken($this->token($this->owner))
            ->deleteJson('/api/v2/accessibility/preferences')
            ->assertOk();
    }

    public function test_owner_can_get_shortcuts_without_explicit_permission(): void
    {
        $this->withToken($this->token($this->owner))
            ->getJson('/api/v2/accessibility/shortcuts')
            ->assertOk();
    }

    public function test_owner_can_update_shortcuts_without_explicit_permission(): void
    {
        $this->withToken($this->token($this->owner))
            ->putJson('/api/v2/accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3']])
            ->assertOk();
    }
}
