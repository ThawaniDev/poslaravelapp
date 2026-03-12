<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use App\Domain\Core\Services\OnboardingService;
use App\Domain\ProviderRegistration\Models\BusinessTypeTemplate;
use App\Domain\ProviderRegistration\Models\OnboardingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOnboardingApiTest extends TestCase
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
            'name'          => 'Test Organization',
            'business_type' => 'retail',
            'country'       => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Branch',
            'business_type'   => 'retail',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->owner = User::create([
            'name'            => 'Store Owner',
            'email'           => 'owner@teststore.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ─── Store CRUD ──────────────────────────────────────────────

    public function test_can_get_my_store(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores/mine');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'business_type',
                    'is_active',
                ],
            ]);

        $this->assertEquals($this->store->id, $response->json('data.id'));
    }

    public function test_can_list_organization_stores(): void
    {
        // Create a second branch
        Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Second Branch',
            'business_type'   => 'retail',
            'currency'        => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_get_store_by_id(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->store->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->store->id)
            ->assertJsonPath('data.name', 'Main Branch');
    }

    public function test_returns_404_for_nonexistent_store(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000099';
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_can_update_store(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}", [
                'name'    => 'Updated Branch',
                'name_ar' => 'الفرع المحدث',
                'city'    => 'Riyadh',
                'phone'   => '+966501234567',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Branch');

        $this->assertDatabaseHas('stores', [
            'id'   => $this->store->id,
            'name' => 'Updated Branch',
            'city' => 'Riyadh',
        ]);
    }

    // ─── Store Settings ──────────────────────────────────────────

    public function test_can_get_store_settings(): void
    {
        // Settings are auto-created by the service
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->store->id}/settings");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tax_label',
                    'tax_rate',
                    'prices_include_tax',
                    'currency_code',
                    'currency_symbol',
                    'allow_negative_stock',
                    'auto_print_receipt',
                ],
            ]);
    }

    public function test_can_update_store_settings(): void
    {
        // Ensure settings exist
        StoreSettings::create([
            'store_id'           => $this->store->id,
            'tax_label'          => 'VAT',
            'tax_rate'           => 15.00,
            'prices_include_tax' => true,
            'currency_code'      => 'SAR',
            'currency_symbol'    => '﷼',
            'decimal_places'     => 2,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
                'tax_rate'       => 5.00,
                'tax_label'      => 'GST',
                'enable_tips'    => true,
                'currency_code'  => 'OMR',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('store_settings', [
            'store_id'    => $this->store->id,
            'tax_label'   => 'GST',
            'enable_tips' => true,
        ]);
    }

    public function test_settings_validation_rejects_invalid_tax_rate(): void
    {
        StoreSettings::create([
            'store_id'      => $this->store->id,
            'tax_rate'      => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
                'tax_rate' => 150, // > 100 — should fail validation
            ]);

        $response->assertUnprocessable();
    }

    // ─── Working Hours ───────────────────────────────────────────

    public function test_can_get_working_hours(): void
    {
        // Create default working hours
        for ($d = 0; $d <= 6; $d++) {
            StoreWorkingHour::create([
                'store_id'    => $this->store->id,
                'day_of_week' => $d,
                'is_open'     => $d !== 5,
                'open_time'   => $d !== 5 ? '09:00' : null,
                'close_time'  => $d !== 5 ? '22:00' : null,
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->store->id}/working-hours");

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
    }

    public function test_can_update_working_hours(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/working-hours", [
                'store_id' => $this->store->id,
                'days' => [
                    ['day_of_week' => 0, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 1, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 2, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 3, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 4, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 5, 'is_open' => false, 'open_time' => null,    'close_time' => null],
                    ['day_of_week' => 6, 'is_open' => true,  'open_time' => '10:00', 'close_time' => '18:00'],
                ],
            ]);

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
        $this->assertDatabaseHas('store_working_hours', [
            'store_id'    => $this->store->id,
            'day_of_week' => 6,
            'open_time'   => '10:00',
        ]);
    }

    public function test_working_hours_validation_rejects_invalid_day(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->store->id}/working-hours", [
                'store_id' => $this->store->id,
                'days' => [
                    ['day_of_week' => 9, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                ],
            ]);

        $response->assertUnprocessable();
    }

    // ─── Business Types ──────────────────────────────────────────

    public function test_can_list_business_types(): void
    {
        // Seed a template
        BusinessTypeTemplate::create([
            'code'          => 'retail',
            'name_en'       => 'Retail Store',
            'name_ar'       => 'متجر تجزئة',
            'icon'          => 'store',
            'template_json' => ['tax_rate' => 15.0],
            'is_active'     => true,
            'display_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/business-types');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_can_apply_business_type(): void
    {
        BusinessTypeTemplate::create([
            'code'          => 'restaurant',
            'name_en'       => 'Restaurant',
            'name_ar'       => 'مطعم',
            'icon'          => 'restaurant',
            'template_json' => [
                'tax_rate'               => 15.0,
                'enable_kitchen_display' => true,
                'enable_tips'            => true,
            ],
            'is_active'     => true,
            'display_order' => 2,
        ]);

        // Ensure settings exist
        StoreSettings::create([
            'store_id'      => $this->store->id,
            'tax_rate'      => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->store->id}/business-type", [
                'business_type' => 'restaurant',
            ]);

        $response->assertOk();

        $this->store->refresh();
        $this->assertEquals('restaurant', $this->store->business_type->value);

        // Verify template settings were applied
        $settings = $this->store->storeSettings;
        $this->assertTrue($settings->enable_kitchen_display);
        $this->assertTrue($settings->enable_tips);
    }

    public function test_apply_invalid_business_type_returns_error(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->store->id}/business-type", [
                'business_type' => 'not_a_real_type',
            ]);

        $response->assertUnprocessable();
    }

    // ─── Onboarding Steps ────────────────────────────────────────

    public function test_can_list_onboarding_steps(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $response->assertOk();

        $steps = $response->json('data');
        $this->assertCount(8, $steps);
        $this->assertEquals('welcome', $steps[0]['key']);
        $this->assertEquals('review', $steps[7]['key']);
    }

    // ─── Onboarding Progress ─────────────────────────────────────

    public function test_can_get_onboarding_progress(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_step',
                    'completed_steps',
                    'is_wizard_completed',
                    'checklist_items',
                ],
            ]);

        $this->assertEquals('welcome', $response->json('data.current_step'));
        $this->assertFalse($response->json('data.is_wizard_completed'));
    }

    public function test_progress_defaults_to_user_store(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/progress');

        $response->assertOk()
            ->assertJsonPath('data.current_step', 'welcome');
    }

    public function test_can_complete_onboarding_step(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step'     => 'welcome',
                'data'     => [],
            ]);

        $response->assertOk();
        $this->assertContains('welcome', $response->json('data.completed_steps'));
        $this->assertEquals('business_info', $response->json('data.current_step'));
    }

    public function test_completing_business_info_step_updates_store(): void
    {
        // Ensure onboarding progress exists
        $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step'     => 'business_info',
                'data'     => [
                    'name'  => 'My Updated Store',
                    'city'  => 'Jeddah',
                    'phone' => '+966509876543',
                ],
            ]);

        $response->assertOk();
        $this->assertContains('business_info', $response->json('data.completed_steps'));

        $this->store->refresh();
        $this->assertEquals('My Updated Store', $this->store->name);
        $this->assertEquals('Jeddah', $this->store->city);
    }

    public function test_completing_tax_step_applies_settings(): void
    {
        // Ensure settings exist first
        StoreSettings::create([
            'store_id'      => $this->store->id,
            'tax_rate'      => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/complete-step', [
                'store_id' => $this->store->id,
                'step'     => 'tax',
                'data'     => [
                    'tax_label'          => 'GST',
                    'tax_rate'           => 5.00,
                    'prices_include_tax' => false,
                ],
            ]);

        $response->assertOk();

        $settings = StoreSettings::where('store_id', $this->store->id)->first();
        $this->assertEquals('GST', $settings->tax_label);
        $this->assertEquals(5.00, (float) $settings->tax_rate);
        $this->assertFalse($settings->prices_include_tax);
    }

    public function test_can_skip_onboarding_wizard(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/skip', [
                'store_id' => $this->store->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_wizard_completed', true);
    }

    public function test_completing_all_steps_marks_wizard_done(): void
    {
        foreach (OnboardingService::STEP_ORDER as $step) {
            $this->withToken($this->token)
                ->postJson('/api/v2/core/onboarding/complete-step', [
                    'store_id' => $this->store->id,
                    'step'     => $step,
                    'data'     => [],
                ]);
        }

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_wizard_completed', true);
    }

    // ─── Onboarding Checklist ────────────────────────────────────

    public function test_can_update_checklist_item(): void
    {
        // Init progress with default checklist
        $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/checklist', [
                'store_id'  => $this->store->id,
                'item_key'  => 'add_first_product',
                'completed' => true,
            ]);

        $response->assertOk();

        $checklist = $response->json('data.checklist_items');
        $this->assertTrue($checklist['add_first_product']['completed']);
        $this->assertNotNull($checklist['add_first_product']['completed_at']);
    }

    public function test_can_dismiss_checklist(): void
    {
        // Init progress
        $this->withToken($this->token)
            ->getJson("/api/v2/core/onboarding/progress?store_id={$this->store->id}");

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/dismiss-checklist', [
                'store_id' => $this->store->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_checklist_dismissed', true);
    }

    public function test_can_reset_onboarding(): void
    {
        // Complete some steps first
        $this->withToken($this->token)->postJson('/api/v2/core/onboarding/complete-step', [
            'store_id' => $this->store->id,
            'step'     => 'welcome',
            'data'     => [],
        ]);
        $this->withToken($this->token)->postJson('/api/v2/core/onboarding/complete-step', [
            'store_id' => $this->store->id,
            'step'     => 'business_info',
            'data'     => [],
        ]);

        // Reset
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/onboarding/reset', [
                'store_id' => $this->store->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.current_step', 'welcome')
            ->assertJsonPath('data.is_wizard_completed', false);

        $this->assertEmpty($response->json('data.completed_steps'));
    }

    // ─── Auth Guard ──────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v2/core/stores/mine');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_onboarding_is_rejected(): void
    {
        $response = $this->getJson('/api/v2/core/onboarding/steps');
        $response->assertUnauthorized();
    }
}
