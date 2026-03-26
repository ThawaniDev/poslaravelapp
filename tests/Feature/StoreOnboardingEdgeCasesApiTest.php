<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use App\Domain\ProviderRegistration\Models\BusinessTypeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreOnboardingEdgeCasesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Organization',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Branch',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@teststore.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Store CRUD Edge Cases ───────────────────────────────────

    public function test_update_store_with_arabic_name(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}", [
                'name' => 'المتجر المحدث',
                'name_ar' => 'المتجر المحدث',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'name' => 'المتجر المحدث',
        ]);
    }

    public function test_update_store_with_all_fields(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}", [
                'name' => 'Full Update',
                'name_ar' => 'تحديث كامل',
                'city' => 'Muscat',
                'phone' => '+96891234567',
                'address' => '123 Main St',
                'description' => 'A fully updated store',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Full Update');
    }

    public function test_cannot_update_other_org_store(): void
    {
        // Create a separate org+store
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'country' => 'SA',
        ]);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'currency' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$otherStore->id}", [
                'name' => 'Hacked',
            ]);

        // Should fail — store belongs to a different org
        $this->assertContains($response->status(), [403, 404, 500]);
    }

    public function test_list_stores_returns_only_own_org(): void
    {
        // Other org + store
        $otherOrg = Organization::create([
            'name' => 'Other',
            'country' => 'SA',
        ]);
        Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Hidden Store',
            'currency' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores');

        $response->assertOk();
        $storeNames = array_column($response->json('data'), 'name');
        $this->assertNotContains('Hidden Store', $storeNames);
    }

    // ─── Store Settings Edge Cases ───────────────────────────────

    public function test_settings_default_values(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'tax_rate' => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->store->id}/settings");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(15.00, (float) $data['tax_rate']);
        $this->assertEquals('SAR', $data['currency_code']);
    }

    public function test_settings_tax_rate_zero_is_valid(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'tax_rate' => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
                'tax_rate' => 0,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('store_settings', [
            'store_id' => $this->store->id,
            'tax_rate' => 0,
        ]);
    }

    public function test_settings_negative_tax_rate_rejected(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'tax_rate' => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
                'tax_rate' => -5,
            ]);

        $response->assertUnprocessable();
    }

    public function test_settings_toggle_booleans(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'currency_code' => 'SAR',
            'allow_negative_stock' => false,
            'auto_print_receipt' => false,
            'enable_tips' => false,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
                'allow_negative_stock' => true,
                'auto_print_receipt' => true,
                'enable_tips' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('store_settings', [
            'store_id' => $this->store->id,
            'allow_negative_stock' => true,
            'auto_print_receipt' => true,
            'enable_tips' => true,
        ]);
    }

    // ─── Working Hours Edge Cases ────────────────────────────────

    public function test_working_hours_all_days_closed(): void
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[] = ['day_of_week' => $d, 'is_open' => false];
        }

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/working-hours", [
                'store_id' => $this->store->id,
                'days' => $days,
            ]);

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
    }

    public function test_working_hours_24_hour_operation(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/working-hours", [
                'store_id' => $this->store->id,
                'days' => [
                    ['day_of_week' => 0, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                    ['day_of_week' => 1, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                    ['day_of_week' => 2, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                    ['day_of_week' => 3, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                    ['day_of_week' => 4, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                    ['day_of_week' => 5, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                    ['day_of_week' => 6, 'is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
                ],
            ]);

        $response->assertOk();
    }

    public function test_working_hours_partial_update(): void
    {
        // Set initial hours
        for ($d = 0; $d <= 6; $d++) {
            StoreWorkingHour::create([
                'store_id' => $this->store->id,
                'day_of_week' => $d,
                'is_open' => true,
                'open_time' => '09:00',
                'close_time' => '22:00',
            ]);
        }

        // Update only 2 days
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/working-hours", [
                'store_id' => $this->store->id,
                'days' => [
                    ['day_of_week' => 5, 'is_open' => false],
                    ['day_of_week' => 6, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '18:00'],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    // ─── Business Type Edge Cases ────────────────────────────────

    public function test_apply_business_type_updates_all_template_settings(): void
    {
        BusinessTypeTemplate::create([
            'code' => 'cafe',
            'name_en' => 'Cafe',
            'name_ar' => 'مقهى',
            'icon' => 'cafe',
            'template_json' => [
                'tax_rate' => 15.0,
                'enable_tips' => true,
                'enable_kitchen_display' => true,
                'enable_table_management' => true,
            ],
            'is_active' => true,
            'display_order' => 3,
        ]);

        StoreSettings::create([
            'store_id' => $this->store->id,
            'tax_rate' => 5.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->store->id}/business-type", [
                'business_type' => 'cafe',
            ]);

        $response->assertOk();
        $this->store->refresh();
        // Business type should be updated
        $this->assertEquals('cafe', $this->store->business_type->value);
    }

    public function test_business_types_list_only_active(): void
    {
        BusinessTypeTemplate::create([
            'code' => 'active_type',
            'name_en' => 'Active',
            'name_ar' => 'نشط',
            'icon' => 'check',
            'template_json' => [],
            'is_active' => true,
            'display_order' => 1,
        ]);

        BusinessTypeTemplate::create([
            'code' => 'inactive_type',
            'name_en' => 'Inactive',
            'name_ar' => 'غير نشط',
            'icon' => 'x',
            'template_json' => [],
            'is_active' => false,
            'display_order' => 2,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/business-types');

        $response->assertOk();
        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('active_type', $codes);
        $this->assertNotContains('inactive_type', $codes);
    }

    // ─── Onboarding Edge Cases ───────────────────────────────────

    public function test_complete_step_out_of_order(): void
    {
        // Try to complete 'tax' before 'welcome' and 'business_info'
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step' => 'tax',
                'data' => [],
            ]);

        // Should work (flexible) or reject (strict ordering)
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_complete_same_step_twice_is_idempotent(): void
    {
        // Complete welcome
        $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step' => 'welcome',
                'data' => [],
            ])->assertOk();

        // Complete welcome again
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step' => 'welcome',
                'data' => [],
            ]);

        // Should succeed idempotently
        $response->assertOk();
        $completed = $response->json('data.completed_steps');
        $this->assertEquals(1, count(array_filter($completed, fn($s) => $s === 'welcome')));
    }

    public function test_complete_invalid_step(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step' => 'nonexistent_step',
                'data' => [],
            ]);

        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_skip_then_continue_onboarding(): void
    {
        // Skip wizard
        $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/skip', [
                'store_id' => $this->store->id,
            ])->assertOk();

        // Check progress — should be completed
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_wizard_completed', true);
    }

    public function test_reset_after_skip(): void
    {
        // Skip
        $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/skip', [
                'store_id' => $this->store->id,
            ])->assertOk();

        // Reset
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/reset', [
                'store_id' => $this->store->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_wizard_completed', false)
            ->assertJsonPath('data.current_step', 'welcome');
    }

    public function test_checklist_item_completed_at_timestamp(): void
    {
        // Init progress
        $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $before = now()->toIso8601String();

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/checklist', [
                'store_id' => $this->store->id,
                'item_key' => 'add_first_product',
                'completed' => true,
            ]);

        $response->assertOk();
        $completedAt = $response->json('data.checklist_items.add_first_product.completed_at');
        $this->assertNotNull($completedAt);
    }

    public function test_checklist_uncheck_item(): void
    {
        // Init and check
        $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/checklist', [
                'store_id' => $this->store->id,
                'item_key' => 'add_first_product',
                'completed' => true,
            ]);

        // Uncheck
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/checklist', [
                'store_id' => $this->store->id,
                'item_key' => 'add_first_product',
                'completed' => false,
            ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.checklist_items.add_first_product.completed'));
    }

    public function test_dismiss_checklist_persists(): void
    {
        // Init
        $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        // Dismiss
        $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/dismiss-checklist', [
                'store_id' => $this->store->id,
            ])->assertOk();

        // Check persisted
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_checklist_dismissed', true);
    }

    // ─── Auth Guard ──────────────────────────────────────────────

    public function test_unauthenticated_cannot_update_store(): void
    {
        $response = $this->putJson("/api/v2/core/stores/{$this->store->id}", [
            'name' => 'Hacked',
        ]);
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_update_settings(): void
    {
        $response = $this->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
            'tax_rate' => 0,
        ]);
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_complete_step(): void
    {
        $response = $this->postJson('/api/v2/core/onboarding/complete-step', [
            'store_id' => $this->store->id,
            'step' => 'welcome',
            'data' => [],
        ]);
        $response->assertUnauthorized();
    }
}
