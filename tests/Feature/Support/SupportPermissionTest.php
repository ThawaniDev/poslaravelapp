<?php

namespace Tests\Feature\Support;

use App\Domain\AdminPanel\Models\AdminPermission;
use App\Domain\AdminPanel\Models\AdminRole;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Support\Models\CannedResponse;
use App\Domain\Support\Models\SupportTicket;
use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BypassPermissionMiddleware;
use Tests\TestCase;

/**
 * Permission matrix tests for the Support Ticket System.
 *
 * Unlike other feature tests, this class restores the REAL permission
 * middleware so that permission enforcement is actually tested.
 *
 * Covers:
 *   - Unauthenticated requests are rejected (401)
 *   - Missing permissions are rejected (403)
 *   - Correct permissions pass (200 / 201)
 *   - Cross-tenant access is blocked (403 / 404)
 */
class SupportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $adminNoPermissions;
    private AdminUser $adminTicketsView;
    private AdminUser $adminTicketsRespond;
    private AdminUser $adminKbManage;
    private AdminUser $adminFullAccess;
    private User $ownerUser;
    private User $staffUser;
    private Store $store;
    private Organization $org;
    private SupportTicket $ticket;
    private CannedResponse $canned;
    private string $adminBase = '/api/v2/admin/support';

    protected function setUp(): void
    {
        parent::setUp();

        // Restore REAL permission middleware so permission checks are enforced.
        // The base TestCase replaces these with BypassPermissionMiddleware.
        $router = app('router');
        $router->aliasMiddleware('permission', CheckPermission::class);

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

        // Store owner — bypasses all permission checks by role
        $this->ownerUser = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@perm-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        // Staff user — no permissions by default (will get specific ones per test)
        $this->staffUser = User::create([
            'name'            => 'Staff',
            'email'           => 'staff@perm-test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);

        $this->adminNoPermissions  = $this->createAdmin('noperm@test.com', []);
        $this->adminTicketsView    = $this->createAdmin('view@test.com', ['tickets.view']);
        $this->adminTicketsRespond = $this->createAdmin('respond@test.com', ['tickets.view', 'tickets.respond']);
        $this->adminKbManage       = $this->createAdmin('kb@test.com', ['kb.manage']);
        $this->adminFullAccess     = $this->createAdmin('full@test.com', [
            'tickets.view', 'tickets.respond', 'tickets.assign', 'kb.manage', 'analytics.view',
        ]);

        $this->ticket = SupportTicket::create([
            'ticket_number'   => 'TKT-2026-0001',
            'organization_id' => $this->org->id,
            'store_id'        => $this->store->id,
            'user_id'         => $this->ownerUser->id,
            'category'        => 'technical',
            'priority'        => 'medium',
            'status'          => 'open',
            'subject'         => 'Permission test ticket',
            'description'     => 'Test description',
        ]);

        $this->canned = CannedResponse::forceCreate([
            'id'         => Str::uuid()->toString(),
            'title'      => 'Test Canned',
            'shortcut'   => '/test-perm',
            'body'       => 'Hello, thank you for contacting us.',
            'body_ar'    => 'مرحباً، شكراً للتواصل.',
            'is_active'  => true,
            'created_at' => now(),
        ]);
    }

    /**
     * Create an AdminUser with a dedicated role that has the given permissions.
     */
    private function createAdmin(string $email, array $permissionNames): AdminUser
    {
        $admin = AdminUser::forceCreate([
            'id'            => Str::uuid()->toString(),
            'name'          => "Admin {$email}",
            'email'         => $email,
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        if (!empty($permissionNames)) {
            // Create a unique role for this admin
            $role = AdminRole::create([
                'name'      => "Test Role {$email}",
                'slug'      => 'test-role-' . Str::random(6),
                'is_system' => false,
            ]);

            // Create / find permissions and attach to role
            foreach ($permissionNames as $name) {
                // Map permission name to a valid group enum value
                $group = match (true) {
                    str_starts_with($name, 'tickets.') || str_starts_with($name, 'kb.') => 'tickets',
                    str_starts_with($name, 'analytics.') => 'analytics',
                    default => 'settings',
                };
                $permission = AdminPermission::firstOrCreate(
                    ['name' => $name],
                    ['group' => $group, 'description' => "Test permission: {$name}"]
                );
                $role->permissions()->attach($permission->id);
            }

            // Assign role to admin (pivot has assigned_at)
            \DB::table('admin_user_roles')->insert([
                'admin_user_id' => $admin->id,
                'admin_role_id' => $role->id,
                'assigned_at'   => now(),
            ]);
        }

        // Flush permission cache so the test picks up fresh permissions
        Cache::forget("admin_user:{$admin->id}:permissions");
        Cache::forget("admin_user:{$admin->id}:is_super_admin");

        return $admin;
    }

    // ═══════════════════════════════════════════════════════════
    //  UNAUTHENTICATED — All admin routes should return 401
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_admin_stats(): void
    {
        $this->getJson("{$this->adminBase}/stats")->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_list_admin_tickets(): void
    {
        $this->getJson("{$this->adminBase}/tickets")->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_view_admin_ticket(): void
    {
        $this->getJson("{$this->adminBase}/tickets/{$this->ticket->id}")->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_create_admin_ticket(): void
    {
        $this->postJson("{$this->adminBase}/tickets", [])->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_list_canned_responses(): void
    {
        $this->getJson("{$this->adminBase}/canned-responses")->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_list_kb_articles(): void
    {
        $this->getJson("{$this->adminBase}/kb")->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN WITHOUT PERMISSIONS — Should return 403
    // ═══════════════════════════════════════════════════════════

    public function test_admin_without_tickets_view_cannot_access_stats(): void
    {
        Sanctum::actingAs($this->adminNoPermissions, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/stats")->assertForbidden();
    }

    public function test_admin_without_tickets_view_cannot_list_tickets(): void
    {
        Sanctum::actingAs($this->adminNoPermissions, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/tickets")->assertForbidden();
    }

    public function test_admin_without_tickets_view_cannot_view_ticket(): void
    {
        Sanctum::actingAs($this->adminNoPermissions, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/tickets/{$this->ticket->id}")->assertForbidden();
    }

    public function test_admin_without_tickets_respond_cannot_create_ticket(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets", [
            'organization_id' => $this->org->id,
            'subject'         => 'Test',
            'description'     => 'Test',
            'category'        => 'technical',
        ])->assertForbidden();
    }

    public function test_admin_without_tickets_respond_cannot_add_message(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/messages", [
            'message_text' => 'Hi there',
        ])->assertForbidden();
    }

    public function test_admin_without_tickets_respond_cannot_change_status(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'resolved',
        ])->assertForbidden();
    }

    public function test_admin_without_tickets_respond_cannot_assign_ticket(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/assign", [
            'assigned_to' => $this->adminTicketsView->id,
        ])->assertForbidden();
    }

    public function test_admin_without_kb_manage_cannot_create_kb_article(): void
    {
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/kb", [
            'title'    => 'Test Article',
            'title_ar' => 'مقال اختبار',
            'slug'     => 'test-article',
            'body'     => 'Content',
            'body_ar'  => 'المحتوى',
            'category' => 'general',
        ])->assertForbidden();
    }

    public function test_admin_without_kb_manage_cannot_create_canned_response(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/canned-responses", [
            'title'   => 'Test',
            'body'    => 'Body',
            'body_ar' => 'الجسم',
        ])->assertForbidden();
    }

    public function test_admin_without_kb_manage_cannot_update_canned_response(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->putJson("{$this->adminBase}/canned-responses/{$this->canned->id}", [
            'title' => 'Updated',
        ])->assertForbidden();
    }

    public function test_admin_without_kb_manage_cannot_delete_canned_response(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->deleteJson("{$this->adminBase}/canned-responses/{$this->canned->id}")->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    //  CORRECT PERMISSIONS — Should succeed
    // ═══════════════════════════════════════════════════════════

    public function test_admin_with_tickets_view_can_access_stats(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/stats")->assertOk();
    }

    public function test_admin_with_tickets_view_can_list_tickets(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/tickets")->assertOk();
    }

    public function test_admin_with_tickets_view_can_view_ticket(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/tickets/{$this->ticket->id}")->assertOk();
    }

    public function test_admin_with_tickets_view_can_list_messages(): void
    {
        Sanctum::actingAs($this->adminTicketsView, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/tickets/{$this->ticket->id}/messages")->assertOk();
    }

    public function test_admin_with_tickets_respond_can_create_ticket(): void
    {
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets", [
            'organization_id' => $this->org->id,
            'subject'         => 'Admin created',
            'description'     => 'Created on behalf',
            'category'        => 'technical',
        ])->assertCreated();
    }

    public function test_admin_with_tickets_respond_can_add_message(): void
    {
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/messages", [
            'message_text' => 'Hello provider',
        ])->assertCreated();
    }

    public function test_admin_with_tickets_respond_can_change_status(): void
    {
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'in_progress',
        ])->assertOk();
    }

    public function test_admin_with_kb_manage_can_list_canned_responses(): void
    {
        Sanctum::actingAs($this->adminKbManage, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/canned-responses")->assertOk();
    }

    public function test_admin_with_tickets_respond_can_list_canned_responses(): void
    {
        // tickets.respond also has access to list canned responses (for quick replies)
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        $this->getJson("{$this->adminBase}/canned-responses")->assertOk();
    }

    public function test_admin_with_kb_manage_can_create_canned_response(): void
    {
        Sanctum::actingAs($this->adminKbManage, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/canned-responses", [
            'title'   => 'New Canned',
            'body'    => 'Thank you for your message.',
            'body_ar' => 'شكراً على رسالتك.',
        ])->assertCreated();
    }

    public function test_admin_with_kb_manage_can_create_kb_article(): void
    {
        Sanctum::actingAs($this->adminKbManage, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/kb", [
            'title'    => 'Getting Started',
            'title_ar' => 'البدء',
            'slug'     => 'getting-started-test',
            'body'     => '<p>Welcome to the POS system.</p>',
            'body_ar'  => '<p>مرحباً بك في نظام نقاط البيع.</p>',
            'category' => 'getting_started',
        ])->assertCreated();
    }

    public function test_admin_with_kb_manage_can_update_kb_article(): void
    {
        Sanctum::actingAs($this->adminKbManage, ['*'], 'admin-api');

        // Create article first
        $article = \App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle::create([
            'title'        => 'Test Article',
            'title_ar'     => 'مقال اختبار',
            'slug'         => 'test-article-perm',
            'body'         => 'Content',
            'body_ar'      => 'المحتوى',
            'category'     => 'general',
            'is_published' => false,
            'sort_order'   => 0,
        ]);

        $this->putJson("{$this->adminBase}/kb/{$article->id}", [
            'title' => 'Updated Title',
        ])->assertOk();
    }

    public function test_admin_with_kb_manage_can_delete_kb_article(): void
    {
        Sanctum::actingAs($this->adminKbManage, ['*'], 'admin-api');

        $article = \App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle::create([
            'title'        => 'Delete Me',
            'title_ar'     => 'احذفني',
            'slug'         => 'delete-me-perm-' . Str::random(4),
            'body'         => 'Content',
            'body_ar'      => 'المحتوى',
            'category'     => 'general',
            'is_published' => false,
            'sort_order'   => 0,
        ]);

        $this->deleteJson("{$this->adminBase}/kb/{$article->id}")->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    //  PROVIDER PERMISSION CHECKS
    // ═══════════════════════════════════════════════════════════

    public function test_provider_requires_auth_to_access_support(): void
    {
        $this->getJson('/api/v2/support/tickets')->assertUnauthorized();
        $this->postJson('/api/v2/support/tickets', [])->assertUnauthorized();
        $this->getJson('/api/v2/support/stats')->assertUnauthorized();
    }

    public function test_provider_cannot_access_other_stores_ticket(): void
    {
        // Create ticket for another store
        $org2 = Organization::create(['name' => 'Org2', 'business_type' => 'grocery', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id, 'name' => 'Other Store',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Other User', 'email' => 'other-perm@test.com',
            'password_hash' => bcrypt('p'), 'store_id' => $store2->id,
            'organization_id' => $org2->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $otherTicket = SupportTicket::create([
            'ticket_number'   => 'TKT-2026-9999',
            'organization_id' => $org2->id,
            'store_id'        => $store2->id,
            'user_id'         => $user2->id,
            'category'        => 'technical',
            'priority'        => 'medium',
            'status'          => 'open',
            'subject'         => 'Other store ticket',
            'description'     => 'Other store description',
        ]);

        // Authenticate as our store's owner and try to access the other store's ticket
        Sanctum::actingAs($this->ownerUser, ['*']);

        $this->getJson("/api/v2/support/tickets/{$otherTicket->id}")->assertNotFound();
    }

    public function test_provider_cannot_close_other_stores_ticket(): void
    {
        $org2 = Organization::create(['name' => 'Org3', 'business_type' => 'grocery', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id, 'name' => 'Org3 Store',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Org3 User', 'email' => 'org3@test.com',
            'password_hash' => bcrypt('p'), 'store_id' => $store2->id,
            'organization_id' => $org2->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $otherTicket = SupportTicket::create([
            'ticket_number'   => 'TKT-2026-8888',
            'organization_id' => $org2->id,
            'store_id'        => $store2->id,
            'user_id'         => $user2->id,
            'category'        => 'billing',
            'priority'        => 'low',
            'status'          => 'open',
            'subject'         => 'Other close test',
            'description'     => 'Should not be closeable by other store',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $this->putJson("/api/v2/support/tickets/{$otherTicket->id}/close")->assertNotFound();
    }

    public function test_provider_cannot_rate_other_stores_resolved_ticket(): void
    {
        $org2 = Organization::create(['name' => 'Org4', 'business_type' => 'grocery', 'country' => 'SA']);
        $store2 = Store::create([
            'organization_id' => $org2->id, 'name' => 'Org4 Store',
            'business_type' => 'grocery', 'currency' => 'SAR',
            'is_active' => true, 'is_main_branch' => true,
        ]);
        $user2 = User::create([
            'name' => 'Org4 User', 'email' => 'org4@test.com',
            'password_hash' => bcrypt('p'), 'store_id' => $store2->id,
            'organization_id' => $org2->id, 'role' => 'owner', 'is_active' => true,
        ]);
        $otherTicket = SupportTicket::create([
            'ticket_number'   => 'TKT-2026-7777',
            'organization_id' => $org2->id,
            'store_id'        => $store2->id,
            'user_id'         => $user2->id,
            'category'        => 'billing',
            'priority'        => 'low',
            'status'          => 'resolved',
            'subject'         => 'Other rate test',
            'description'     => 'Should not be rateable by other store',
            'resolved_at'     => now(),
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $this->postJson("/api/v2/support/tickets/{$otherTicket->id}/rate", [
            'rating' => 5,
        ])->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN CANNOT IMPERSONATE PROVIDER API
    // ═══════════════════════════════════════════════════════════

    public function test_admin_token_cannot_access_provider_support_routes(): void
    {
        Sanctum::actingAs($this->adminFullAccess, ['*'], 'admin-api');

        // Admin trying to use provider API should get 401 (different guard)
        $response = $this->getJson('/api/v2/support/tickets');
        // Should fail because admin tokens don't authenticate on the sanctum (provider) guard
        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    //  STATUS CHANGE VALIDATION
    // ═══════════════════════════════════════════════════════════

    public function test_admin_cannot_set_invalid_status(): void
    {
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
            'status' => 'pending', // not a valid status
        ])->assertUnprocessable();
    }

    public function test_admin_status_change_accepts_all_valid_statuses(): void
    {
        Sanctum::actingAs($this->adminTicketsRespond, ['*'], 'admin-api');

        foreach (['in_progress', 'resolved', 'closed', 'open'] as $status) {
            $response = $this->postJson("{$this->adminBase}/tickets/{$this->ticket->id}/status", [
                'status' => $status,
            ]);
            $response->assertOk();
        }
    }
}
