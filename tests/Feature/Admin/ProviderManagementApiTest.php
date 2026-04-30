<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\ProviderRegistration\Models\ProviderNote;
use App\Domain\ProviderRegistration\Models\ProviderRegistration;
use App\Domain\ProviderSubscription\Models\ProviderLimitOverride;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Organization $org;
    private Store $store;

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

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Store Listing ────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_list_stores_returns_paginated_results(): void
    {
        $response = $this
            ->getJson('/api/v2/admin/providers/stores');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'stores',
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.pagination.total'));
    }

    public function test_list_stores_search_by_name(): void
    {
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee Shop',
            'business_type' => 'restaurant',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $response = $this
            ->getJson('/api/v2/admin/providers/stores?search=Coffee');

        $response->assertOk();
        $stores = collect($response->json('data.stores'));
        $this->assertTrue($stores->contains(fn ($s) => str_contains($s['name'], 'Coffee')));
    }

    public function test_list_stores_filter_by_active_status(): void
    {
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => false,
            'is_main_branch' => false,
        ]);

        $response = $this
            ->getJson('/api/v2/admin/providers/stores?is_active=false');

        $response->assertOk();
        $stores = collect($response->json('data.stores'));
        $this->assertTrue($stores->every(fn ($s) => $s['is_active'] === false));
    }

    public function test_list_stores_requires_auth(): void
    {
        // Reset auth to test unauthenticated access
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v2/admin/providers/stores');

        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Store Detail ─────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_show_store_detail(): void
    {
        $response = $this
            ->getJson("/api/v2/admin/providers/stores/{$this->store->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->store->id)
            ->assertJsonPath('data.name', 'Test Store')
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'id', 'name', 'business_type', 'currency', 'is_active', 'organization',
                ],
            ]);
    }

    public function test_show_store_not_found(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this
            ->getJson("/api/v2/admin/providers/stores/{$fakeId}");

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Store Metrics ────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_store_metrics_returns_data(): void
    {
        $response = $this
            ->getJson("/api/v2/admin/providers/stores/{$this->store->id}/metrics");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store_id', $this->store->id)
            ->assertJsonPath('data.store_name', 'Test Store')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'store_id', 'store_name', 'is_active', 'subscription',
                    'active_overrides', 'internal_notes_count',
                ],
            ]);
    }

    public function test_store_metrics_with_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
            'is_active' => true,
        ]);

        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this
            ->getJson("/api/v2/admin/providers/stores/{$this->store->id}/metrics");

        $response->assertOk()
            ->assertJsonPath('data.subscription.status', 'active');
    }

    public function test_store_metrics_not_found(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this
            ->getJson("/api/v2/admin/providers/stores/{$fakeId}/metrics");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Suspend / Activate ───────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_suspend_store(): void
    {
        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/suspend", [
                'reason' => 'Policy violation',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Store suspended successfully');

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'is_active' => false,
        ]);

        // Verify activity log was created
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'store.suspend',
            'entity_type' => 'store',
            'entity_id' => $this->store->id,
        ]);
    }

    public function test_activate_store(): void
    {
        // First suspend
        $this->store->update(['is_active' => false]);

        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/activate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Store activated successfully');

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'store.activate',
            'entity_id' => $this->store->id,
        ]);
    }

    public function test_suspend_nonexistent_store_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$fakeId}/suspend");

        $response->assertNotFound();
    }

    public function test_activate_nonexistent_store_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$fakeId}/activate");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Manual Store Creation ────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_create_store_manually(): void
    {
        $response = $this
            ->postJson('/api/v2/admin/providers/stores/create', [
                'organization_name' => 'New Org',
                'organization_business_type' => 'restaurant',
                'organization_country' => 'SA',
                'store_name' => 'New Restaurant',
                'store_currency' => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Store created successfully')
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'organization' => ['id', 'name'],
                    'store' => ['id', 'name', 'business_type', 'currency'],
                ],
            ]);

        $this->assertDatabaseHas('organizations', ['name' => 'New Org']);
        $this->assertDatabaseHas('stores', ['name' => 'New Restaurant']);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'store.create_manual',
        ]);
    }

    public function test_create_store_requires_mandatory_fields(): void
    {
        $response = $this
            ->postJson('/api/v2/admin/providers/stores/create', []);

        $response->assertStatus(422);
    }

    public function test_create_store_with_defaults(): void
    {
        $response = $this
            ->postJson('/api/v2/admin/providers/stores/create', [
                'organization_name' => 'Default Org',
                'store_name' => 'Default Store',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.store.currency', 'SAR');
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Data Export ──────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_export_stores(): void
    {
        $response = $this
            ->postJson('/api/v2/admin/providers/stores/export');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'export',
                    'count',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.count'));
    }

    public function test_export_stores_with_filter(): void
    {
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Inactive Export',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => false,
            'is_main_branch' => false,
        ]);

        $response = $this
            ->postJson('/api/v2/admin/providers/stores/export', [
                'business_type' => 'grocery',
            ]);

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Registration Queue ───────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_list_registrations(): void
    {
        ProviderRegistration::forceCreate([
            'organization_name' => 'Pending Corp',
            'owner_name' => 'John',
            'owner_email' => 'john@test.com',
            'owner_phone' => '+96812345678',
            'status' => 'pending',
        ]);

        $response = $this
            ->getJson('/api/v2/admin/providers/registrations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'registrations',
                    'pagination',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.pagination.total'));
    }

    public function test_list_registrations_filter_by_status(): void
    {
        ProviderRegistration::forceCreate([
            'organization_name' => 'Approved Corp',
            'owner_name' => 'Jane',
            'owner_email' => 'jane@test.com',
            'owner_phone' => '+96812345679',
            'status' => 'approved',
        ]);
        ProviderRegistration::forceCreate([
            'organization_name' => 'Pending Co',
            'owner_name' => 'Bob',
            'owner_email' => 'bob@test.com',
            'owner_phone' => '+96812345680',
            'status' => 'pending',
        ]);

        $response = $this
            ->getJson('/api/v2/admin/providers/registrations?status=pending');

        $response->assertOk();
        $regs = collect($response->json('data.registrations'));
        $this->assertTrue($regs->every(fn ($r) => $r['status'] === 'pending'));
    }

    public function test_list_registrations_search(): void
    {
        ProviderRegistration::forceCreate([
            'organization_name' => 'Unique Search Corp',
            'owner_name' => 'Alice',
            'owner_email' => 'alice@unique.com',
            'owner_phone' => '+96812345681',
            'status' => 'pending',
        ]);

        $response = $this
            ->getJson('/api/v2/admin/providers/registrations?search=Unique');

        $response->assertOk();
        $regs = collect($response->json('data.registrations'));
        $this->assertTrue($regs->contains(fn ($r) => str_contains($r['organization_name'], 'Unique')));
    }

    public function test_approve_registration(): void
    {
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'Approvable Corp',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@approvable.com',
            'owner_phone' => '+96812345682',
            'status' => 'pending',
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/registrations/{$reg->id}/approve");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $this->admin->id)
            ->assertJsonPath('message', 'Registration approved successfully');

        $this->assertDatabaseHas('provider_registrations', [
            'id' => $reg->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'registration.approve',
            'entity_id' => $reg->id,
        ]);
    }

    public function test_reject_registration(): void
    {
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'Rejectable Corp',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@rejectable.com',
            'owner_phone' => '+96812345683',
            'status' => 'pending',
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/registrations/{$reg->id}/reject", [
                'rejection_reason' => 'Incomplete documentation',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Incomplete documentation');

        $this->assertDatabaseHas('provider_registrations', [
            'id' => $reg->id,
            'status' => 'rejected',
        ]);
    }

    public function test_reject_registration_requires_reason(): void
    {
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'No Reason Corp',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@noreason.com',
            'owner_phone' => '+96812345684',
            'status' => 'pending',
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/registrations/{$reg->id}/reject", []);

        $response->assertStatus(422);
    }

    public function test_cannot_approve_already_approved(): void
    {
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'Already Approved',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@already.com',
            'owner_phone' => '+96812345685',
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/registrations/{$reg->id}/approve");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_reject_already_rejected(): void
    {
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'Already Rejected',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@rejected.com',
            'owner_phone' => '+96812345686',
            'status' => 'rejected',
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/registrations/{$reg->id}/reject", [
                'rejection_reason' => 'Test',
            ]);

        $response->assertStatus(422);
    }

    public function test_approve_nonexistent_registration(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this
            ->postJson("/api/v2/admin/providers/registrations/{$fakeId}/approve");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Internal Notes ───────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_add_note(): void
    {
        $response = $this
            ->postJson('/api/v2/admin/providers/notes', [
                'organization_id' => $this->org->id,
                'note_text' => 'Customer requested premium support.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization_id', $this->org->id)
            ->assertJsonPath('data.admin_user_id', $this->admin->id)
            ->assertJsonPath('data.note_text', 'Customer requested premium support.');

        $this->assertDatabaseHas('provider_notes', [
            'organization_id' => $this->org->id,
            'note_text' => 'Customer requested premium support.',
        ]);
    }

    public function test_add_note_requires_fields(): void
    {
        $response = $this
            ->postJson('/api/v2/admin/providers/notes', []);

        $response->assertStatus(422);
    }

    public function test_list_notes(): void
    {
        ProviderNote::forceCreate([
            'organization_id' => $this->org->id,
            'admin_user_id' => $this->admin->id,
            'note_text' => 'First note',
            'created_at' => now(),
        ]);
        ProviderNote::forceCreate([
            'organization_id' => $this->org->id,
            'admin_user_id' => $this->admin->id,
            'note_text' => 'Second note',
            'created_at' => now(),
        ]);

        $response = $this
            ->getJson("/api/v2/admin/providers/notes/{$this->org->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $notes = $response->json('data');
        $this->assertCount(2, $notes);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Limit Overrides ──────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_set_limit_override(): void
    {
        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/limits", [
                'limit_key' => 'max_products',
                'override_value' => 500,
                'reason' => 'Premium customer',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization_id', $this->org->id)
            ->assertJsonPath('data.limit_key', 'max_products')
            ->assertJsonPath('data.override_value', 500);

        $this->assertDatabaseHas('provider_limit_overrides', [
            'organization_id' => $this->org->id,
            'limit_key' => 'max_products',
            'override_value' => 500,
        ]);
    }

    public function test_update_existing_limit_override(): void
    {
        ProviderLimitOverride::forceCreate([
            'organization_id' => $this->org->id,
            'limit_key' => 'max_cashiers',
            'override_value' => 10,
            'set_by' => $this->admin->id,
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/limits", [
                'limit_key' => 'max_cashiers',
                'override_value' => 20,
                'reason' => 'Upgraded plan',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.override_value', 20);

        $this->assertDatabaseCount('provider_limit_overrides', 1);
    }

    public function test_list_limit_overrides(): void
    {
        ProviderLimitOverride::forceCreate([
            'organization_id' => $this->org->id,
            'limit_key' => 'max_products',
            'override_value' => 500,
            'set_by' => $this->admin->id,
        ]);
        ProviderLimitOverride::forceCreate([
            'organization_id' => $this->org->id,
            'limit_key' => 'max_cashiers',
            'override_value' => 10,
            'set_by' => $this->admin->id,
        ]);

        $response = $this
            ->getJson("/api/v2/admin/providers/stores/{$this->store->id}/limits");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $overrides = $response->json('data');
        $this->assertCount(2, $overrides);
    }

    public function test_remove_limit_override(): void
    {
        ProviderLimitOverride::forceCreate([
            'organization_id' => $this->org->id,
            'limit_key' => 'max_products',
            'override_value' => 500,
            'set_by' => $this->admin->id,
        ]);

        $response = $this
            ->deleteJson("/api/v2/admin/providers/stores/{$this->store->id}/limits/max_products");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Limit override removed');

        $this->assertDatabaseMissing('provider_limit_overrides', [
            'organization_id' => $this->org->id,
            'limit_key' => 'max_products',
        ]);
    }

    public function test_remove_nonexistent_limit_override(): void
    {
        $response = $this
            ->deleteJson("/api/v2/admin/providers/stores/{$this->store->id}/limits/00000000-0000-0000-0000-000000000099");

        $response->assertNotFound();
    }

    public function test_set_limit_override_requires_fields(): void
    {
        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/limits", []);

        $response->assertStatus(422);
    }

    public function test_set_limit_override_with_expiry(): void
    {
        $expiresAt = now()->addDays(30)->toIso8601String();

        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/limits", [
                'limit_key' => 'max_terminals',
                'override_value' => 5,
                'reason' => 'Temporary upgrade',
                'expires_at' => $expiresAt,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.limit_key', 'max_terminals')
            ->assertJsonPath('data.override_value', 5);

        $this->assertNotNull($response->json('data.expires_at'));
    }

    // ═══════════════════════════════════════════════════════════
    // ─── Activity Log Verification ────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_activity_log_created_on_suspend(): void
    {
        $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/suspend", [
                'reason' => 'Test suspension',
            ]);

        $log = AdminActivityLog::where('action', 'store.suspend')
            ->where('entity_id', $this->store->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->admin->id, $log->admin_user_id);
        $this->assertEquals('store', $log->entity_type);
        $this->assertArrayHasKey('reason', $log->details);
        $this->assertEquals('Test suspension', $log->details['reason']);
    }

    public function test_activity_log_created_on_note(): void
    {
        $this
            ->postJson('/api/v2/admin/providers/notes', [
                'organization_id' => $this->org->id,
                'note_text' => 'Activity log test note',
            ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'provider_note.create',
            'admin_user_id' => $this->admin->id,
        ]);
    }

    public function test_activity_log_created_on_limit_override(): void
    {
        $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/limits", [
                'limit_key' => 'max_storage_gb',
                'override_value' => 100,
            ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'limit_override.set',
            'admin_user_id' => $this->admin->id,
        ]);
    }

    public function test_activity_log_created_on_manual_store_creation(): void
    {
        $this
            ->postJson('/api/v2/admin/providers/stores/create', [
                'organization_name' => 'Logged Org',
                'store_name' => 'Logged Store',
            ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'store.create_manual',
        ]);
    }
}
