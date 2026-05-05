<?php

namespace Tests\Feature\Label;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\Subscription\Models\PlanFeatureToggle;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive filter/search/pagination tests for Barcode Label Printing API.
 * Tests: template search, type filter, history date range, template_id filter,
 * per_page parameter, industry-specific preset seeding, UpdateLabelTemplateRequest
 * validation, and audit-level edge cases.
 */
class LabelFilterApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'Filter Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Filter Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $this->user = User::create([
            'name'            => 'Filter User',
            'email'           => 'filter@labels.test',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $this->token = $this->user->createToken('t')->plainTextToken;

        $this->_seedActiveSubscription();
    }

    private function _seedActiveSubscription(): void
    {
        $plan = SubscriptionPlan::create([
            'name'         => 'Test Plan',
            'slug'         => 'test-plan',
            'monthly_price' => 0,
            'is_active'    => true,
            'sort_order'   => 1,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key'          => 'barcode_label_printing',
            'is_enabled'           => true,
        ]);
        StoreSubscription::create([
            'organization_id'       => $this->org->id,
            'subscription_plan_id'  => $plan->id,
            'status'                => 'active',
            'billing_cycle'         => 'monthly',
            'current_period_start'  => now(),
            'current_period_end'    => now()->addMonth(),
        ]);
    }

    private function makeTemplate(array $attrs = []): LabelTemplate
    {
        return LabelTemplate::create(array_merge([
            'organization_id' => $this->org->id,
            'name'            => 'Template ' . uniqid(),
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => [],
            'is_preset'       => false,
            'is_default'      => false,
            'created_by'      => $this->user->id,
            'sync_version'    => 1,
        ], $attrs));
    }

    private function makeHistory(array $attrs = []): LabelPrintHistory
    {
        return LabelPrintHistory::create(array_merge([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
            'printed_at'    => now(),
        ], $attrs));
    }

    // ─── Template search filter ───────────────────────────────

    public function test_template_list_search_by_name(): void
    {
        $this->makeTemplate(['name' => 'Barcode Wide Label']);
        $this->makeTemplate(['name' => 'Small Price Tag']);
        $this->makeTemplate(['name' => 'Barcode Narrow Label']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates?search=Barcode');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('Barcode', $item['name']);
        }
    }

    public function test_template_search_returns_empty_when_no_match(): void
    {
        $this->makeTemplate(['name' => 'Standard Product']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates?search=XYZ_NONEXISTENT');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_template_search_is_case_insensitive(): void
    {
        $this->makeTemplate(['name' => 'Product Barcode']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates?search=barcode');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ─── Template type filter ─────────────────────────────────

    public function test_template_list_filter_by_type_preset(): void
    {
        $this->makeTemplate(['name' => 'My Custom', 'is_preset' => false]);
        $this->makeTemplate(['name' => 'System Preset', 'is_preset' => true]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates?type=preset');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_preset']);
    }

    public function test_template_list_filter_by_type_custom(): void
    {
        $this->makeTemplate(['name' => 'My Custom 1', 'is_preset' => false]);
        $this->makeTemplate(['name' => 'My Custom 2', 'is_preset' => false]);
        $this->makeTemplate(['name' => 'System Preset', 'is_preset' => true]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates?type=custom');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $item) {
            $this->assertFalse($item['is_preset']);
        }
    }

    public function test_template_list_combined_search_and_type_filter(): void
    {
        $this->makeTemplate(['name' => 'Barcode Preset', 'is_preset' => true]);
        $this->makeTemplate(['name' => 'Barcode Custom', 'is_preset' => false]);
        $this->makeTemplate(['name' => 'Different Name', 'is_preset' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates?search=Barcode&type=custom');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Barcode Custom', $data[0]['name']);
    }

    // ─── Print history date range filter ──────────────────────

    public function test_print_history_filter_by_from_date(): void
    {
        $this->makeHistory(['product_count' => 1, 'printed_at' => now()->subDays(30)]);
        $this->makeHistory(['product_count' => 2, 'printed_at' => now()->subDays(5)]);
        $this->makeHistory(['product_count' => 3, 'printed_at' => now()]);

        $from = now()->subDays(10)->toDateString();
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/labels/print-history?from={$from}");

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(2, $rows);
        $counts = collect($rows)->pluck('product_count')->sort()->values()->all();
        $this->assertEquals([2, 3], $counts);
    }

    public function test_print_history_filter_by_to_date(): void
    {
        $this->makeHistory(['product_count' => 1, 'printed_at' => now()->subDays(30)]);
        $this->makeHistory(['product_count' => 2, 'printed_at' => now()]);

        $to = now()->subDays(15)->toDateString();
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/labels/print-history?to={$to}");

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['product_count']);
    }

    public function test_print_history_filter_by_date_range(): void
    {
        $this->makeHistory(['product_count' => 1, 'printed_at' => now()->subDays(30)]);
        $this->makeHistory(['product_count' => 2, 'printed_at' => now()->subDays(7)]);
        $this->makeHistory(['product_count' => 3, 'printed_at' => now()]);

        $from = now()->subDays(10)->toDateString();
        $to   = now()->subDays(3)->toDateString();
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/labels/print-history?from={$from}&to={$to}");

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows[0]['product_count']);
    }

    public function test_print_history_filter_by_template_id(): void
    {
        $targetTemplate = $this->makeTemplate(['name' => 'Target Tpl']);
        $otherTemplate  = $this->makeTemplate(['name' => 'Other Tpl']);

        $this->makeHistory(['template_id' => $targetTemplate->id, 'product_count' => 5]);
        $this->makeHistory(['template_id' => $otherTemplate->id, 'product_count' => 9]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/labels/print-history?template_id={$targetTemplate->id}");

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertEquals(5, $rows[0]['product_count']);
    }

    // ─── Print history per_page parameter ────────────────────

    public function test_print_history_respects_per_page_param(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->makeHistory(['product_count' => $i, 'printed_at' => now()->subSeconds($i)]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history?per_page=5');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(5, $data['data']);
        $this->assertEquals(15, $data['total']);
        $this->assertEquals(5, $data['per_page']);
        $this->assertEquals(3, $data['last_page']);
    }

    public function test_print_history_per_page_max_100(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history?per_page=200');

        $response->assertStatus(422);
    }

    // ─── UpdateLabelTemplateRequest validation ─────────────────

    public function test_update_validates_label_width_min(): void
    {
        $template = $this->makeTemplate(['name' => 'Validate Me']);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", [
                'label_width_mm' => 5, // below 20mm minimum
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('label_width_mm', $response->json('errors'));
    }

    public function test_update_validates_label_width_max(): void
    {
        $template = $this->makeTemplate(['name' => 'Validate Me']);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", [
                'label_width_mm' => 999, // above 200mm maximum
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('label_width_mm', $response->json('errors'));
    }

    public function test_update_validates_label_height_min(): void
    {
        $template = $this->makeTemplate(['name' => 'Validate Me']);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", [
                'label_height_mm' => 3, // below 15mm minimum
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('label_height_mm', $response->json('errors'));
    }

    public function test_update_allows_partial_payload(): void
    {
        $template = $this->makeTemplate(['name' => 'Partial Update', 'label_width_mm' => 50]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", [
                'name' => 'New Name Only',
            ]);

        $response->assertOk();
        $this->assertEquals('New Name Only', $response->json('data.name'));
        $this->assertEquals(50, $response->json('data.label_width_mm'));
    }

    // ─── Industry-specific preset auto-seeding ────────────────

    public function test_pharmacy_org_gets_pharmacy_preset_on_auto_seed(): void
    {
        $pharmacyOrg = Organization::create([
            'name'          => 'Pharmacy X',
            'business_type' => 'pharmacy',
            'country'       => 'SA',
        ]);
        $pharmacyStore = Store::create([
            'organization_id' => $pharmacyOrg->id,
            'name'            => 'Pharmacy Store',
            'business_type'   => 'pharmacy',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $pharmacyUser = User::create([
            'name'            => 'Pharmacist',
            'email'           => 'pharmacist@test.test',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $pharmacyStore->id,
            'organization_id' => $pharmacyOrg->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $pharmacyToken = $pharmacyUser->createToken('t')->plainTextToken;

        // Give the pharmacy org a subscription
        $plan = SubscriptionPlan::first();
        StoreSubscription::create([
            'organization_id'      => $pharmacyOrg->id,
            'subscription_plan_id' => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);

        $response = $this->withToken($pharmacyToken)
            ->getJson('/api/v2/labels/templates/presets');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        // Pharmacy preset should exist (larger for drug stickers)
        $this->assertNotEmpty($names, 'Expected auto-seeded presets for pharmacy org');
        $this->assertGreaterThan(3, count($names), 'Expected pharmacy-specific presets beyond the 3 core presets');
    }

    public function test_jewelry_org_gets_jewelry_preset_on_auto_seed(): void
    {
        $jewelryOrg = Organization::create([
            'name'          => 'Jewels R Us',
            'business_type' => 'jewelry',
            'country'       => 'SA',
        ]);
        $jewelryStore = Store::create([
            'organization_id' => $jewelryOrg->id,
            'name'            => 'Jewelry Store',
            'business_type'   => 'jewelry',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $jewelryUser = User::create([
            'name'            => 'Jeweler',
            'email'           => 'jeweler@test.test',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $jewelryStore->id,
            'organization_id' => $jewelryOrg->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $jewelryToken = $jewelryUser->createToken('t')->plainTextToken;

        $plan = SubscriptionPlan::first();
        StoreSubscription::create([
            'organization_id'      => $jewelryOrg->id,
            'subscription_plan_id' => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);

        $response = $this->withToken($jewelryToken)
            ->getJson('/api/v2/labels/templates/presets');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertNotEmpty($names);
        $this->assertGreaterThan(3, count($names), 'Expected jewelry-specific presets beyond core presets');
    }

    // ─── Pagination page 2 ────────────────────────────────────

    public function test_print_history_page_2_contains_correct_records(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->makeHistory([
                'product_count' => $i,
                'printed_at'    => now()->subSeconds($i),
            ]);
        }

        $p2 = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history?per_page=10&page=2');

        $p2->assertOk();
        $data = $p2->json('data');
        $this->assertCount(10, $data['data']);
        $this->assertEquals(2, $data['current_page']);
        $this->assertEquals(25, $data['total']);
    }

    public function test_print_history_page_3_is_partial(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->makeHistory([
                'product_count' => $i,
                'printed_at'    => now()->subSeconds($i),
            ]);
        }

        $p3 = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history?per_page=10&page=3');

        $p3->assertOk();
        $this->assertCount(5, $p3->json('data.data'));
    }

    // ─── Validation on history record ────────────────────────

    public function test_record_print_history_validates_invalid_date_from(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history?from=not-a-date');

        $response->assertStatus(422);
    }

    public function test_record_print_history_validates_invalid_template_uuid(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history?template_id=not-a-uuid');

        $response->assertStatus(422);
    }

    // ─── Permission: labels.print ─────────────────────────────

    public function test_record_print_history_response_contains_store_id(): void
    {
        $template = $this->makeTemplate(['name' => 'Tpl']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id'  => $template->id,
                'product_count' => 4,
                'total_labels'  => 8,
                'printer_name'  => 'Test Printer',
            ]);

        $response->assertStatus(201);
        $this->assertEquals($this->store->id, $response->json('data.store_id'));
    }

    // ─── Default template visibility ─────────────────────────

    public function test_template_list_default_appears_first(): void
    {
        $this->makeTemplate(['name' => 'Alpha', 'is_default' => false]);
        $this->makeTemplate(['name' => 'Zeta Default', 'is_default' => true]);
        $this->makeTemplate(['name' => 'Beta', 'is_default' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates');

        $response->assertOk();
        $this->assertEquals('Zeta Default', $response->json('data.0.name'));
    }
}
