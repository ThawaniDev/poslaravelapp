<?php

namespace Tests\Unit\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use App\Domain\Core\Services\OnboardingService;
use App\Domain\Core\Services\StoreService;
use App\Domain\ProviderRegistration\Models\BusinessTypeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreOnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    private StoreService $storeService;
    private OnboardingService $onboardingService;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeService = app(StoreService::class);
        $this->onboardingService = app(OnboardingService::class);

        $this->org = Organization::create([
            'name' => 'Test Org',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'slug' => 'main-' . Str::random(4),
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
    }

    // ─── StoreService ────────────────────────────────────────────

    public function test_get_store_loads_relationships(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'currency_code' => 'SAR',
        ]);

        $store = $this->storeService->getStore($this->store->id);

        $this->assertTrue($store->relationLoaded('storeSettings'));
        $this->assertTrue($store->relationLoaded('organization'));
    }

    public function test_list_stores_for_org(): void
    {
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch 2',
            'slug' => 'branch2-' . Str::random(4),
            'currency' => 'SAR',
        ]);

        $stores = $this->storeService->listStores($this->org->id);
        $this->assertCount(2, $stores);
    }

    public function test_list_stores_orders_main_branch_first(): void
    {
        Store::create([
            'organization_id' => $this->org->id,
            'name' => 'AAA Branch',
            'slug' => 'aaa-' . Str::random(4),
            'currency' => 'SAR',
            'is_main_branch' => false,
        ]);

        $stores = $this->storeService->listStores($this->org->id);
        $this->assertTrue($stores->first()->is_main_branch);
    }

    public function test_update_store(): void
    {
        $updated = $this->storeService->updateStore($this->store, [
            'name' => 'Renamed Store',
            'city' => 'Muscat',
        ]);

        $this->assertEquals('Renamed Store', $updated->name);
        $this->assertEquals('Muscat', $updated->city);
    }

    public function test_deactivate_store(): void
    {
        $store = $this->storeService->deactivateStore($this->store);
        $this->assertFalse($store->is_active);
    }

    // ─── Settings ────────────────────────────────────────────────

    public function test_get_settings_creates_defaults(): void
    {
        $settings = $this->storeService->getSettings($this->store->id);
        $this->assertNotNull($settings);
        $this->assertEquals($this->store->id, $settings->store_id);
    }

    public function test_update_settings(): void
    {
        StoreSettings::create([
            'store_id' => $this->store->id,
            'tax_rate' => 15.00,
            'currency_code' => 'SAR',
        ]);

        $settings = $this->storeService->updateSettings($this->store->id, [
            'tax_rate' => 5.00,
            'enable_tips' => true,
        ]);

        $this->assertEquals(5.00, (float) $settings->tax_rate);
        $this->assertTrue($settings->enable_tips);
    }

    // ─── OnboardingService ───────────────────────────────────────

    public function test_get_progress_initializes(): void
    {
        $progress = $this->onboardingService->getProgress($this->store->id);

        $this->assertFalse($progress->is_wizard_completed);
        $this->assertEquals('welcome', $progress->current_step->value ?? $progress->current_step);
        $this->assertEmpty($progress->completed_steps);
    }

    public function test_complete_step_advances(): void
    {
        $progress = $this->onboardingService->completeStep($this->store->id, 'welcome');

        $completedSteps = $progress->completed_steps;
        $this->assertContains('welcome', $completedSteps);
        $this->assertNotEquals('welcome', $progress->current_step->value ?? $progress->current_step);
    }

    public function test_complete_step_is_idempotent(): void
    {
        $this->onboardingService->completeStep($this->store->id, 'welcome');
        $progress = $this->onboardingService->completeStep($this->store->id, 'welcome');

        $welcomeCount = count(array_filter(
            $progress->completed_steps,
            fn($s) => $s === 'welcome'
        ));
        $this->assertEquals(1, $welcomeCount);
    }

    public function test_all_steps_completed_marks_wizard_done(): void
    {
        foreach (OnboardingService::STEP_ORDER as $step) {
            $this->onboardingService->completeStep($this->store->id, $step);
        }

        $progress = $this->onboardingService->getProgress($this->store->id);
        $this->assertTrue($progress->is_wizard_completed);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_skip_wizard(): void
    {
        $progress = $this->onboardingService->skipWizard($this->store->id);
        $this->assertTrue($progress->is_wizard_completed);
    }

    public function test_step_order_has_8_steps(): void
    {
        $this->assertCount(8, OnboardingService::STEP_ORDER);
        $this->assertEquals('welcome', OnboardingService::STEP_ORDER[0]);
        $this->assertEquals('review', OnboardingService::STEP_ORDER[7]);
    }

    public function test_checklist_update(): void
    {
        $progress = $this->onboardingService->getProgress($this->store->id);

        $updated = $this->onboardingService->updateChecklistItem(
            $this->store->id,
            'add_first_product',
            true
        );

        $checklist = $updated->checklist_items;
        $this->assertTrue($checklist['add_first_product']['completed']);
    }

    public function test_dismiss_checklist(): void
    {
        $progress = $this->onboardingService->getProgress($this->store->id);

        $updated = $this->onboardingService->dismissChecklist($this->store->id);
        $this->assertTrue($updated->is_checklist_dismissed);
    }

    public function test_reset_onboarding(): void
    {
        // Complete some steps
        $this->onboardingService->completeStep($this->store->id, 'welcome');
        $this->onboardingService->completeStep($this->store->id, 'business_info');

        $reset = $this->onboardingService->resetOnboarding($this->store->id);

        $this->assertFalse($reset->is_wizard_completed);
        $this->assertEmpty($reset->completed_steps);
        $this->assertEquals('welcome', $reset->current_step->value ?? $reset->current_step);
    }
}
