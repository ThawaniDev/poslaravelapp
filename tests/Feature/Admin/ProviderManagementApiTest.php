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
            ->assertJsonPath('data.registration.status', 'approved')
            ->assertJsonPath('data.registration.reviewed_by', $this->admin->id)
            ->assertJsonPath('message', 'Registration approved successfully')
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'registration', 'organization', 'store', 'user',
                ],
            ]);

        $this->assertDatabaseHas('provider_registrations', [
            'id' => $reg->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Approvable Corp',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@approvable.com',
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

    // ═══════════════════════════════════════════════════════════
    // ─── Impersonation ────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_start_impersonation_creates_session(): void
    {
        // Need an owner user in this store
        $user = \App\Domain\Auth\Models\User::forceCreate([
            'store_id'      => $this->store->id,
            'organization_id' => $this->org->id,
            'name'          => 'Store Owner',
            'email'         => 'owner@teststore.com',
            'phone'         => '+96600000001',
            'password_hash' => bcrypt('pass'),
            'role'          => 'owner',
            'is_active'     => true,
        ]);

        $response = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/impersonate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['session_id', 'token', 'expires_at', 'target_user', 'store_name', 'organization_name'],
            ]);

        $this->assertDatabaseHas('impersonation_sessions', [
            'admin_user_id'  => $this->admin->id,
            'target_user_id' => $user->id,
            'store_id'       => $this->store->id,
        ]);
    }

    public function test_end_impersonation(): void
    {
        $user = \App\Domain\Auth\Models\User::forceCreate([
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'name'            => 'Store Owner 2',
            'email'           => 'owner2@teststore.com',
            'phone'           => '+96600000002',
            'password_hash'   => bcrypt('pass'),
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        // Start session
        $startResp = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/impersonate");
        $token = $startResp->json('data.token');

        $response = $this
            ->postJson('/api/v2/admin/providers/impersonate/end', ['token' => $token]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Impersonation session ended');

        $this->assertDatabaseHas('impersonation_sessions', [
            'admin_user_id' => $this->admin->id,
        ]);
        // ended_at should be set
        $session = \App\Domain\ProviderRegistration\Models\ImpersonationSession::where('token', $token)->first();
        $this->assertNotNull($session->ended_at);
    }

    public function test_extend_impersonation(): void
    {
        $user = \App\Domain\Auth\Models\User::forceCreate([
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'name'            => 'Store Owner 3',
            'email'           => 'owner3@teststore.com',
            'phone'           => '+96600000003',
            'password_hash'   => bcrypt('pass'),
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $startResp = $this
            ->postJson("/api/v2/admin/providers/stores/{$this->store->id}/impersonate");
        $token = $startResp->json('data.token');

        $response = $this
            ->postJson('/api/v2/admin/providers/impersonate/extend', ['token' => $token]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['expires_at']]);
    }

    public function test_list_notes_returns_admin_user_name(): void
    {
        \App\Domain\ProviderRegistration\Models\ProviderNote::forceCreate([
            'organization_id' => $this->org->id,
            'admin_user_id'   => $this->admin->id,
            'note_text'       => 'Test note with admin name',
        ]);

        $response = $this->getJson("/api/v2/admin/providers/notes/{$this->org->id}");

        $response->assertOk();
        $notes = $response->json('data');
        $this->assertNotEmpty($notes);
        $this->assertArrayHasKey('admin_user_name', $notes[0]);
        $this->assertEquals($this->admin->name, $notes[0]['admin_user_name']);
    }

    public function test_list_limit_overrides_returns_set_by_name(): void
    {
        \App\Domain\ProviderSubscription\Models\ProviderLimitOverride::forceCreate([
            'organization_id' => $this->org->id,
            'limit_key'       => 'max_products',
            'override_value'  => 999,
            'set_by'          => $this->admin->id,
        ]);

        $response = $this->getJson("/api/v2/admin/providers/stores/{$this->store->id}/limits");

        $response->assertOk();
        $overrides = $response->json('data');
        $this->assertNotEmpty($overrides);
        $this->assertArrayHasKey('set_by_name', $overrides[0]);
        $this->assertEquals($this->admin->name, $overrides[0]['set_by_name']);
    }

    public function test_store_metrics_comprehensive(): void
    {
        $response = $this->getJson("/api/v2/admin/providers/stores/{$this->store->id}/metrics");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'store_id', 'store_name', 'is_active',
                    'organization' => ['id', 'name'],
                    'usage' => ['staff_count', 'products_count', 'registers_count', 'branches_count', 'delivery_platforms_count', 'recent_orders_30d'],
                    'registers', 'delivery_platforms', 'branches',
                    'limit_overrides', 'active_overrides', 'internal_notes_count',
                ],
            ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: suspend_reason + suspended_at Persistence ───────
    // ═══════════════════════════════════════════════════════════

    public function test_suspend_store_saves_reason_and_timestamp(): void
    {
        $response = $this->postJson("/api/v2/admin/providers/stores/{$this->store->id}/suspend", [
            'reason' => 'Fraudulent activity detected',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->store->refresh();
        $this->assertFalse($this->store->is_active);
        $this->assertEquals('Fraudulent activity detected', $this->store->suspend_reason);
        $this->assertNotNull($this->store->suspended_at);
    }

    public function test_activate_clears_suspend_reason(): void
    {
        // Pre-suspend via DB
        $this->store->update([
            'is_active'      => false,
            'suspend_reason' => 'Old reason',
            'suspended_at'   => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v2/admin/providers/stores/{$this->store->id}/activate");

        $response->assertOk()->assertJsonPath('success', true);

        $this->store->refresh();
        $this->assertTrue($this->store->is_active);
        $this->assertNull($this->store->suspend_reason);
        $this->assertNull($this->store->suspended_at);
    }

    public function test_suspend_store_without_reason_is_allowed(): void
    {
        $response = $this->postJson("/api/v2/admin/providers/stores/{$this->store->id}/suspend", []);

        $response->assertOk()->assertJsonPath('success', true);

        $this->store->refresh();
        $this->assertFalse($this->store->is_active);
        $this->assertNull($this->store->suspend_reason);
    }

    public function test_store_detail_includes_suspend_reason_after_suspend(): void
    {
        $this->store->update([
            'is_active'      => false,
            'suspend_reason' => 'Billing overdue',
            'suspended_at'   => now()->subHour(),
        ]);

        $response = $this->getJson("/api/v2/admin/providers/stores/{$this->store->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.suspend_reason', 'Billing overdue');

        $this->assertNotNull($response->json('data.suspended_at'));
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: StoreAdminResource completeness ─────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_store_detail_includes_all_resource_fields(): void
    {
        $this->store->update([
            'phone'      => '+966501234567',
            'email'      => 'store@example.com',
            'city'       => 'Riyadh',
            'cr_number'  => 'CR123456',
            'vat_number' => 'VAT987654',
        ]);

        $response = $this->getJson("/api/v2/admin/providers/stores/{$this->store->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'business_type', 'currency', 'is_active',
                    'is_main_branch', 'suspend_reason', 'suspended_at',
                    'city', 'phone', 'email', 'cr_number', 'vat_number',
                    'organization', 'created_at', 'updated_at',
                ],
            ])
            ->assertJsonPath('data.phone', '+966501234567')
            ->assertJsonPath('data.email', 'store@example.com')
            ->assertJsonPath('data.cr_number', 'CR123456')
            ->assertJsonPath('data.vat_number', 'VAT987654')
            ->assertJsonPath('data.city', 'Riyadh');
    }

    public function test_store_detail_includes_active_subscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name'          => 'Business',
            'slug'          => 'business',
            'monthly_price' => 49.99,
            'annual_price'  => 499.99,
            'is_active'     => true,
        ]);

        StoreSubscription::create([
            'organization_id'      => $this->org->id,
            'subscription_plan_id' => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);

        $response = $this->getJson("/api/v2/admin/providers/stores/{$this->store->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'active_subscription' => ['plan_name', 'status', 'billing_cycle', 'current_period_end'],
                ],
            ])
            ->assertJsonPath('data.active_subscription.plan_name', 'Business')
            ->assertJsonPath('data.active_subscription.status', 'active');
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: create_store with owner creates user ─────────────
    // ═══════════════════════════════════════════════════════════

    public function test_create_store_with_owner_fields_creates_user(): void
    {
        $response = $this->postJson('/api/v2/admin/providers/stores/create', [
            'organization_name' => 'Owner Test Org',
            'store_name'        => 'Owner Test Store',
            'owner_name'        => 'Owner Person',
            'owner_email'       => 'owner.person@ownertest.com',
            'owner_phone'       => '+966501234568',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['organization', 'store', 'user', 'temp_password'],
            ]);

        $this->assertNotNull($response->json('data.temp_password'));
        $this->assertEquals('owner.person@ownertest.com', $response->json('data.user.email'));

        $this->assertDatabaseHas('users', [
            'email' => 'owner.person@ownertest.com',
            'name'  => 'Owner Person',
        ]);
    }

    public function test_create_store_without_owner_returns_null_user(): void
    {
        $response = $this->postJson('/api/v2/admin/providers/stores/create', [
            'organization_name' => 'No Owner Org',
            'store_name'        => 'No Owner Store',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user', null)
            ->assertJsonPath('data.temp_password', null);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: approve registration returns temp_password ──────
    // ═══════════════════════════════════════════════════════════

    public function test_approve_registration_returns_temp_password(): void
    {
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'TempPass Corp',
            'owner_name'        => 'Pass Owner',
            'owner_email'       => 'passowner@temppass.com',
            'owner_phone'       => '+96812345690',
            'status'            => 'pending',
        ]);

        $response = $this->postJson("/api/v2/admin/providers/registrations/{$reg->id}/approve");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['temp_password']]);

        $tempPassword = $response->json('data.temp_password');
        $this->assertNotNull($tempPassword);
        $this->assertIsString($tempPassword);
        $this->assertGreaterThanOrEqual(8, strlen($tempPassword));
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: Case-insensitive registration search ─────────────
    // ═══════════════════════════════════════════════════════════

    public function test_registrations_search_is_case_insensitive(): void
    {
        ProviderRegistration::forceCreate([
            'organization_name' => 'CaseSensitiveTest',
            'owner_name'        => 'John Doe',
            'owner_email'       => 'john.doe.case@test.com',
            'owner_phone'       => '+96812345691',
            'status'            => 'pending',
        ]);

        // Search with lowercase — should still find CaseSensitiveTest
        $response = $this->getJson('/api/v2/admin/providers/registrations?search=casesensitivetest');

        $response->assertOk();
        $regs = collect($response->json('data.registrations'));
        $this->assertTrue($regs->contains(fn ($r) => strtolower($r['organization_name']) === strtolower('CaseSensitiveTest')));
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: Limit override with past expiry is invalid ───────
    // ═══════════════════════════════════════════════════════════

    public function test_set_limit_override_with_past_expiry_fails(): void
    {
        $response = $this->postJson("/api/v2/admin/providers/stores/{$this->store->id}/limits", [
            'limit_key'      => 'max_products',
            'override_value' => 100,
            'expires_at'     => now()->subDay()->toIso8601String(),
        ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: Pagination edge cases ────────────────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_list_stores_pagination_defaults(): void
    {
        $response = $this->getJson('/api/v2/admin/providers/stores?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ],
            ]);

        $this->assertLessThanOrEqual(5, count($response->json('data.stores')));
    }

    public function test_list_stores_page_2_returns_correct_offset(): void
    {
        // Create 20 extra stores
        for ($i = 1; $i <= 20; $i++) {
            Store::create([
                'organization_id' => $this->org->id,
                'name'            => "Pagination Store {$i}",
                'business_type'   => 'grocery',
                'currency'        => 'SAR',
                'is_active'       => true,
                'is_main_branch'  => false,
            ]);
        }

        $responsePage1 = $this->getJson('/api/v2/admin/providers/stores?per_page=10&page=1');
        $responsePage2 = $this->getJson('/api/v2/admin/providers/stores?per_page=10&page=2');

        $responsePage1->assertOk();
        $responsePage2->assertOk();

        $ids1 = collect($responsePage1->json('data.stores'))->pluck('id')->toArray();
        $ids2 = collect($responsePage2->json('data.stores'))->pluck('id')->toArray();

        // No overlap between pages
        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: E2E Full Registration Workflow ───────────────────
    // ═══════════════════════════════════════════════════════════

    public function test_full_registration_to_impersonation_e2e(): void
    {
        // 1. Submit registration
        $reg = ProviderRegistration::forceCreate([
            'organization_name' => 'E2E Corp',
            'owner_name'        => 'E2E Owner',
            'owner_email'       => 'e2e.owner@e2ecorp.com',
            'owner_phone'       => '+96812345699',
            'status'            => 'pending',
        ]);

        $this->assertDatabaseHas('provider_registrations', [
            'organization_name' => 'E2E Corp',
            'status'            => 'pending',
        ]);

        // 2. Approve registration → creates org + store + user + temp_password
        $approveResp = $this->postJson("/api/v2/admin/providers/registrations/{$reg->id}/approve");

        $approveResp->assertOk()->assertJsonPath('success', true);
        $approvedOrgId = $approveResp->json('data.organization.id');
        $storeId       = $approveResp->json('data.store.id');
        $tempPassword  = $approveResp->json('data.temp_password');

        $this->assertNotNull($storeId);
        $this->assertNotNull($tempPassword);
        $this->assertDatabaseHas('provider_registrations', ['id' => $reg->id, 'status' => 'approved']);
        $this->assertDatabaseHas('organizations', ['id' => $approvedOrgId]);
        $this->assertDatabaseHas('stores', ['id' => $storeId]);
        $this->assertDatabaseHas('users', ['email' => 'e2e.owner@e2ecorp.com']);

        // 3. Add an internal note
        $noteResp = $this->postJson('/api/v2/admin/providers/notes', [
            'organization_id' => $approvedOrgId,
            'note_text'       => 'Approved after document verification.',
        ]);
        $noteResp->assertStatus(201);

        // 4. Set a limit override
        $limitResp = $this->postJson("/api/v2/admin/providers/stores/{$storeId}/limits", [
            'limit_key'      => 'max_products',
            'override_value' => 1000,
            'reason'         => 'Large catalog expected',
        ]);
        $limitResp->assertOk();

        // 5. View store detail — should include suspend_reason=null, organization, cr_number
        $detailResp = $this->getJson("/api/v2/admin/providers/stores/{$storeId}");
        $detailResp->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.suspend_reason', null);

        // 6. Get store metrics
        $metricsResp = $this->getJson("/api/v2/admin/providers/stores/{$storeId}/metrics");
        $metricsResp->assertOk()
            ->assertJsonPath('data.store_id', $storeId)
            ->assertJsonPath('data.active_overrides', 1);

        // 7. Impersonate
        $impersonateResp = $this->postJson("/api/v2/admin/providers/stores/{$storeId}/impersonate");
        $impersonateResp->assertOk();
        $token = $impersonateResp->json('data.token');
        $this->assertNotNull($token);

        // 8. End impersonation
        $endResp = $this->postJson('/api/v2/admin/providers/impersonate/end', ['token' => $token]);
        $endResp->assertOk()->assertJsonPath('success', true);

        // 9. Suspend store
        $suspendResp = $this->postJson("/api/v2/admin/providers/stores/{$storeId}/suspend", [
            'reason' => 'Periodic suspension for compliance',
        ]);
        $suspendResp->assertOk()->assertJsonPath('data.is_active', false);

        // Verify DB
        $updatedStore = Store::find($storeId);
        $this->assertFalse($updatedStore->is_active);
        $this->assertEquals('Periodic suspension for compliance', $updatedStore->suspend_reason);
        $this->assertNotNull($updatedStore->suspended_at);

        // 10. Reactivate
        $activateResp = $this->postJson("/api/v2/admin/providers/stores/{$storeId}/activate");
        $activateResp->assertOk()->assertJsonPath('data.is_active', true);

        $updatedStore->refresh();
        $this->assertTrue($updatedStore->is_active);
        $this->assertNull($updatedStore->suspend_reason);
    }

    // ═══════════════════════════════════════════════════════════
    // ─── New: Unauthenticated access on all key endpoints ──────
    // ═══════════════════════════════════════════════════════════

    public function test_all_endpoints_require_authentication(): void
    {
        app('auth')->forgetGuards();

        $endpoints = [
            ['GET', "/api/v2/admin/providers/stores"],
            ['GET', "/api/v2/admin/providers/stores/{$this->store->id}"],
            ['GET', "/api/v2/admin/providers/stores/{$this->store->id}/metrics"],
            ['POST', "/api/v2/admin/providers/stores/{$this->store->id}/suspend"],
            ['POST', "/api/v2/admin/providers/stores/{$this->store->id}/activate"],
            ['POST', "/api/v2/admin/providers/stores/create"],
            ['GET', "/api/v2/admin/providers/registrations"],
            ['POST', "/api/v2/admin/providers/notes"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $this->assertEquals(401, $response->getStatusCode(), "Expected 401 for {$method} {$url}");
        }
    }
}
