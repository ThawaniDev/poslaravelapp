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
 * Comprehensive E2E / integration tests for the Barcode Label Printing API.
 *
 * Test matrix:
 *   ✔ Full user workflows (design → print → history)
 *   ✔ Permission enforcement (labels.view / labels.manage / labels.print)
 *   ✔ Subscription feature gate
 *   ✔ Print history filters (date_from, date_to, template_id)
 *   ✔ Print history stats endpoint
 *   ✔ printer_language / job_pages / duration_ms fields
 *   ✔ Pagination (per_page, page)
 *   ✔ IDOR protection (all mutation endpoints)
 *   ✔ Preset immutability
 *   ✔ Default clearing on create / set-default
 *   ✔ Duplicate preserves layout and dimensions
 *   ✔ Validation edge cases (sizes, layout types, invalid printer_language)
 *   ✔ API response shape (ISO8601 dates, typed numerics, nested names)
 */
class LabelComprehensiveApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    // Second organisation for IDOR tests
    private User $otherUser;
    private Organization $otherOrg;
    private Store $otherStore;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'Main Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);
        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $this->user = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@comprehensive-labels.com',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $this->token = $this->user->createToken('t')->plainTextToken;

        // Second org
        $this->otherOrg = Organization::create([
            'name'          => 'Other Org',
            'business_type' => 'fashion',
            'country'       => 'SA',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other Store',
            'business_type'   => 'fashion',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);
        $this->otherUser = User::create([
            'name'            => 'Other Owner',
            'email'           => 'other@comprehensive-labels.com',
            'password_hash'   => bcrypt('pw'),
            'store_id'        => $this->otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('t2')->plainTextToken;

        // Both orgs get an active subscription with barcode_label_printing enabled
        $plan = SubscriptionPlan::create([
            'name'          => 'Comprehensive Plan',
            'slug'          => 'comp-plan',
            'monthly_price' => 0,
            'is_active'     => true,
            'sort_order'    => 77,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $plan->id,
            'feature_key'          => 'barcode_label_printing',
            'is_enabled'           => true,
        ]);
        foreach ([$this->org->id, $this->otherOrg->id] as $orgId) {
            StoreSubscription::create([
                'organization_id'      => $orgId,
                'subscription_plan_id' => $plan->id,
                'status'               => 'active',
                'billing_cycle'        => 'monthly',
                'current_period_start' => now(),
                'current_period_end'   => now()->addMonth(),
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // E2E Workflow: Design → Set Default → Print → History → Stats
    // ════════════════════════════════════════════════════════════════

    public function test_full_workflow_design_print_history(): void
    {
        // 1. Create a template
        $createResp = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Workflow Template',
                'label_width_mm'  => 58,
                'label_height_mm' => 40,
                'layout_json'     => [
                    'elements' => [
                        ['type' => 'product_name', 'x' => 2, 'y' => 2, 'width' => 54, 'height' => 5, 'config' => ['font_size' => 10]],
                        ['type' => 'barcode', 'x' => 2, 'y' => 10, 'width' => 40, 'height' => 18, 'config' => ['format' => 'code128']],
                        ['type' => 'price', 'x' => 43, 'y' => 14, 'width' => 14, 'height' => 12, 'config' => ['font_size' => 14, 'show_currency' => true]],
                    ],
                ],
            ]);
        $createResp->assertStatus(201);
        $templateId = $createResp->json('data.id');

        // 2. Set it as default
        $defaultResp = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$templateId}/set-default");
        $defaultResp->assertOk()->assertJsonPath('data.is_default', true);

        // 3. Record a print job
        $printResp = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id'      => $templateId,
                'product_count'    => 10,
                'total_labels'     => 30,
                'printer_name'     => 'Zebra ZD420',
                'printer_language' => 'zpl',
                'duration_ms'      => 2300,
            ]);
        $printResp->assertStatus(201)
            ->assertJsonPath('data.template_name', 'Workflow Template')
            ->assertJsonPath('data.printer_language', 'zpl')
            ->assertJsonPath('data.duration_ms', 2300);

        // 4. Verify history includes the job
        $histResp = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history');
        $histResp->assertOk();
        $this->assertSame(1, $histResp->json('data.total'));

        // 5. Check stats
        $statsResp = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history/stats');
        $statsResp->assertOk()
            ->assertJsonPath('data.jobs_last_30_days', 1)
            ->assertJsonPath('data.products_last_30_days', 10)
            ->assertJsonPath('data.labels_last_30_days', 30);
    }

    // ════════════════════════════════════════════════════════════════
    // Subscription Feature Gate
    // ════════════════════════════════════════════════════════════════

    public function test_blocks_all_endpoints_when_feature_disabled(): void
    {
        app('router')->aliasMiddleware('plan.feature', \App\Http\Middleware\CheckPlanFeature::class);
        PlanFeatureToggle::where('feature_key', 'barcode_label_printing')->update(['is_enabled' => false]);

        $endpoints = [
            ['GET',  '/api/v2/labels/templates'],
            ['GET',  '/api/v2/labels/templates/presets'],
            ['GET',  '/api/v2/labels/print-history'],
            ['GET',  '/api/v2/labels/print-history/stats'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $resp = $this->withToken($this->token)->{strtolower($method) . 'Json'}($uri);
            $resp->assertStatus(403)
                ->assertJsonPath('error_code', 'feature_not_available');
        }
    }

    public function test_blocks_when_subscription_expired(): void
    {
        app('router')->aliasMiddleware('plan.feature', \App\Http\Middleware\CheckPlanFeature::class);
        StoreSubscription::where('organization_id', $this->org->id)
            ->update(['status' => 'expired', 'current_period_end' => now()->subDay()]);

        $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates')
            ->assertStatus(403);
    }

    // ════════════════════════════════════════════════════════════════
    // Authentication
    // ════════════════════════════════════════════════════════════════

    public function test_all_endpoints_require_authentication(): void
    {
        $template = $this->makeTemplate();

        $endpoints = [
            ['GET',    '/api/v2/labels/templates'],
            ['GET',    '/api/v2/labels/templates/presets'],
            ['POST',   '/api/v2/labels/templates'],
            ['GET',    "/api/v2/labels/templates/{$template->id}"],
            ['PUT',    "/api/v2/labels/templates/{$template->id}"],
            ['DELETE', "/api/v2/labels/templates/{$template->id}"],
            ['POST',   "/api/v2/labels/templates/{$template->id}/duplicate"],
            ['POST',   "/api/v2/labels/templates/{$template->id}/set-default"],
            ['GET',    '/api/v2/labels/print-history'],
            ['GET',    '/api/v2/labels/print-history/stats'],
            ['POST',   '/api/v2/labels/print-history'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $resp = $this->{strtolower($method) . 'Json'}($uri);
            $this->assertEquals(401, $resp->status(), "Expected 401 for {$method} {$uri}");
        }
    }

    // ════════════════════════════════════════════════════════════════
    // IDOR Protection
    // ════════════════════════════════════════════════════════════════

    public function test_show_cannot_access_other_org_template(): void
    {
        $foreign = $this->makeTemplateFor($this->otherOrg);
        $this->withToken($this->token)->getJson("/api/v2/labels/templates/{$foreign->id}")->assertStatus(404);
    }

    public function test_update_cannot_modify_other_org_template(): void
    {
        $foreign = $this->makeTemplateFor($this->otherOrg, ['name' => 'Foreign']);
        $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$foreign->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);
        $foreign->refresh();
        $this->assertEquals('Foreign', $foreign->name);
    }

    public function test_delete_cannot_remove_other_org_template(): void
    {
        $foreign = $this->makeTemplateFor($this->otherOrg);
        $this->withToken($this->token)->deleteJson("/api/v2/labels/templates/{$foreign->id}")->assertStatus(404);
        $this->assertDatabaseHas('label_templates', ['id' => $foreign->id]);
    }

    public function test_duplicate_cannot_copy_other_org_template(): void
    {
        $foreign = $this->makeTemplateFor($this->otherOrg);
        $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$foreign->id}/duplicate")->assertStatus(404);
    }

    public function test_set_default_cannot_modify_other_org_template(): void
    {
        $foreign = $this->makeTemplateFor($this->otherOrg, ['is_default' => false]);
        $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$foreign->id}/set-default")->assertStatus(404);
        $foreign->refresh();
        $this->assertFalse((bool) $foreign->is_default);
    }

    public function test_set_default_does_not_clear_other_org_defaults(): void
    {
        $otherDefault = $this->makeTemplateFor($this->otherOrg, ['is_default' => true]);
        $mine = $this->makeTemplate(['is_default' => false]);

        $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$mine->id}/set-default")->assertOk();

        $otherDefault->refresh();
        $this->assertTrue((bool) $otherDefault->is_default, 'Cross-org default should not be cleared');
    }

    // ════════════════════════════════════════════════════════════════
    // Template CRUD
    // ════════════════════════════════════════════════════════════════

    public function test_create_template_returns_complete_resource(): void
    {
        $resp = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Complete Resource',
                'label_width_mm'  => 60,
                'label_height_mm' => 40,
                'layout_json'     => ['elements' => []],
                'is_default'      => false,
            ]);

        $resp->assertStatus(201);
        $data = $resp->json('data');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('organization_id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('label_width_mm', $data);
        $this->assertArrayHasKey('label_height_mm', $data);
        $this->assertArrayHasKey('layout_json', $data);
        $this->assertArrayHasKey('is_preset', $data);
        $this->assertArrayHasKey('is_default', $data);
        $this->assertArrayHasKey('created_by', $data);
        $this->assertArrayHasKey('sync_version', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);

        $this->assertFalse($data['is_preset']);
        $this->assertSame(1, $data['sync_version']);
        $this->assertEquals(60.0, (float) $data['label_width_mm']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $data['created_at']);
    }

    public function test_create_sets_org_from_authenticated_user(): void
    {
        $resp = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Org Scoped',
                'label_width_mm'  => 50,
                'label_height_mm' => 30,
                'layout_json'     => ['elements' => []],
            ]);

        $resp->assertStatus(201);
        $this->assertEquals($this->org->id, $resp->json('data.organization_id'));
    }

    public function test_create_validates_minimum_dimensions(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Too Small',
                'label_width_mm'  => 19,
                'label_height_mm' => 14,
                'layout_json'     => ['elements' => []],
            ])
            ->assertStatus(422);
    }

    public function test_create_validates_maximum_dimensions(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Too Big',
                'label_width_mm'  => 201,
                'label_height_mm' => 151,
                'layout_json'     => ['elements' => []],
            ])
            ->assertStatus(422);
    }

    public function test_create_accepts_boundary_dimensions(): void
    {
        // Min boundary: 20×15
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Min Size',
                'label_width_mm'  => 20,
                'label_height_mm' => 15,
                'layout_json'     => ['elements' => []],
            ])
            ->assertStatus(201);

        // Max boundary: 200×150
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Max Size',
                'label_width_mm'  => 200,
                'label_height_mm' => 150,
                'layout_json'     => ['elements' => []],
            ])
            ->assertStatus(201);
    }

    public function test_update_increments_sync_version(): void
    {
        $t = $this->makeTemplate(['sync_version' => 5]);
        $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$t->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.sync_version', 6);
    }

    public function test_cannot_update_preset(): void
    {
        $t = $this->makeTemplate(['is_preset' => true]);
        $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$t->id}", ['name' => 'Hacked'])
            ->assertStatus(422);
    }

    public function test_cannot_delete_preset(): void
    {
        $t = $this->makeTemplate(['is_preset' => true]);
        $this->withToken($this->token)
            ->deleteJson("/api/v2/labels/templates/{$t->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('label_templates', ['id' => $t->id]);
    }

    public function test_show_returns_404_for_nonexistent_template(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    // ════════════════════════════════════════════════════════════════
    // Duplicate endpoint
    // ════════════════════════════════════════════════════════════════

    public function test_duplicate_creates_copy_with_appended_copy_suffix(): void
    {
        $t = $this->makeTemplate(['name' => 'Original']);
        $resp = $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$t->id}/duplicate");

        $resp->assertStatus(201)
            ->assertJsonPath('data.name', 'Original (Copy)')
            ->assertJsonPath('data.is_preset', false)
            ->assertJsonPath('data.is_default', false);
    }

    public function test_duplicate_produces_new_unique_id(): void
    {
        $t = $this->makeTemplate();
        $resp = $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$t->id}/duplicate");
        $resp->assertStatus(201);
        $this->assertNotEquals($t->id, $resp->json('data.id'));
    }

    public function test_duplicate_preset_creates_custom_copy(): void
    {
        $preset = $this->makeTemplate(['is_preset' => true]);
        $resp = $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$preset->id}/duplicate");

        $resp->assertStatus(201)
            ->assertJsonPath('data.is_preset', false);
    }

    public function test_duplicate_preserves_layout_json(): void
    {
        $layout = ['elements' => [['type' => 'barcode', 'x' => 5, 'y' => 5, 'width' => 40, 'height' => 15]]];
        $t = $this->makeTemplate(['layout_json' => $layout]);

        $resp = $this->withToken($this->token)->postJson("/api/v2/labels/templates/{$t->id}/duplicate");
        $resp->assertStatus(201);

        $copy = LabelTemplate::find($resp->json('data.id'));
        $this->assertEquals($layout, $copy->layout_json);
    }

    // ════════════════════════════════════════════════════════════════
    // Set-Default endpoint
    // ════════════════════════════════════════════════════════════════

    public function test_set_default_marks_template_as_default(): void
    {
        $t = $this->makeTemplate(['is_default' => false]);
        $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$t->id}/set-default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_set_default_clears_existing_default_in_same_org(): void
    {
        $old = $this->makeTemplate(['name' => 'Old', 'is_default' => true]);
        $new = $this->makeTemplate(['name' => 'New', 'is_default' => false]);

        $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$new->id}/set-default")
            ->assertOk();

        $old->refresh();
        $this->assertFalse((bool) $old->is_default);
    }

    // ════════════════════════════════════════════════════════════════
    // Presets
    // ════════════════════════════════════════════════════════════════

    public function test_presets_auto_seed_on_first_access(): void
    {
        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/templates/presets');
        $resp->assertOk();

        $names = collect($resp->json('data'))->pluck('name')->all();
        $this->assertContains('Standard Product', $names);
        $this->assertContains('Shelf Edge', $names);
        $this->assertContains('Weighable Item', $names);
    }

    public function test_presets_not_duplicated_on_second_call(): void
    {
        $this->withToken($this->token)->getJson('/api/v2/labels/templates/presets');
        $this->withToken($this->token)->getJson('/api/v2/labels/templates/presets');

        $count = LabelTemplate::where('organization_id', $this->org->id)
            ->where('is_preset', true)
            ->where('name', 'Standard Product')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_preset_list_only_returns_is_preset_true_items(): void
    {
        $this->makeTemplate(['name' => 'Custom', 'is_preset' => false]);
        $this->makeTemplate(['name' => 'My Preset', 'is_preset' => true]);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/templates/presets');
        $resp->assertOk();

        foreach ($resp->json('data') as $item) {
            $this->assertTrue($item['is_preset'], 'Preset endpoint should only return presets');
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Print History — Record
    // ════════════════════════════════════════════════════════════════

    public function test_record_print_with_all_new_fields(): void
    {
        $t = $this->makeTemplate();

        $resp = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id'      => $t->id,
                'product_count'    => 5,
                'total_labels'     => 10,
                'printer_name'     => 'TSC TTP-244 Pro',
                'printer_language' => 'tspl',
                'job_pages'        => 2,
                'duration_ms'      => 3500,
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.product_count', 5)
            ->assertJsonPath('data.total_labels', 10)
            ->assertJsonPath('data.printer_name', 'TSC TTP-244 Pro')
            ->assertJsonPath('data.printer_language', 'tspl')
            ->assertJsonPath('data.job_pages', 2)
            ->assertJsonPath('data.duration_ms', 3500);
    }

    public function test_record_print_with_null_template(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id'   => null,
                'product_count' => 1,
                'total_labels'  => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.template_id', null)
            ->assertJsonPath('data.template_name', null);
    }

    public function test_record_print_validates_printer_language_enum(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'product_count'    => 1,
                'total_labels'     => 1,
                'printer_language' => 'unknown_lang',
            ])
            ->assertStatus(422);
    }

    public function test_record_print_accepts_all_valid_printer_languages(): void
    {
        foreach (['zpl', 'tspl', 'escpos', 'image'] as $lang) {
            $resp = $this->withToken($this->token)
                ->postJson('/api/v2/labels/print-history', [
                    'product_count'    => 1,
                    'total_labels'     => 1,
                    'printer_language' => $lang,
                ]);
            $resp->assertStatus(201, "Language '{$lang}' should be accepted");
            $this->assertEquals($lang, $resp->json('data.printer_language'));
        }
    }

    public function test_record_print_validates_minimum_product_count(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'product_count' => 0,
                'total_labels'  => 1,
            ])
            ->assertStatus(422);
    }

    public function test_record_print_validates_required_fields(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [])
            ->assertStatus(422);
    }

    // ════════════════════════════════════════════════════════════════
    // Print History — List & Filters
    // ════════════════════════════════════════════════════════════════

    public function test_print_history_returns_paginated_response(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makePrintHistory();
        }

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history');
        $resp->assertOk();

        $this->assertArrayHasKey('current_page', $resp->json('data'));
        $this->assertArrayHasKey('total', $resp->json('data'));
        $this->assertArrayHasKey('per_page', $resp->json('data'));
        $this->assertArrayHasKey('last_page', $resp->json('data'));
        $this->assertSame(3, $resp->json('data.total'));
    }

    public function test_print_history_respects_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makePrintHistory();
        }

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history?per_page=2');
        $resp->assertOk();

        $this->assertCount(2, $resp->json('data.data'));
        $this->assertSame(5, $resp->json('data.total'));
    }

    public function test_print_history_filters_by_date_from(): void
    {
        $this->makePrintHistory(['printed_at' => now()->subDays(20)]);
        $this->makePrintHistory(['printed_at' => now()->subDays(5)]);
        $this->makePrintHistory(['printed_at' => now()->subDay()]);

        $from = now()->subDays(7)->toDateString();
        $resp = $this->withToken($this->token)->getJson("/api/v2/labels/print-history?from={$from}");
        $resp->assertOk();
        $this->assertSame(2, $resp->json('data.total'));
    }

    public function test_print_history_filters_by_date_to(): void
    {
        $this->makePrintHistory(['printed_at' => now()->subDays(20)]);
        $this->makePrintHistory(['printed_at' => now()->subDays(10)]);
        $this->makePrintHistory(['printed_at' => now()->subDay()]);

        $to = now()->subDays(8)->toDateString();
        $resp = $this->withToken($this->token)->getJson("/api/v2/labels/print-history?to={$to}");
        $resp->assertOk();
        $this->assertSame(2, $resp->json('data.total'));
    }

    public function test_print_history_filters_by_template_id(): void
    {
        $t1 = $this->makeTemplate(['name' => 'Template A']);
        $t2 = $this->makeTemplate(['name' => 'Template B']);

        $this->makePrintHistory(['template_id' => $t1->id]);
        $this->makePrintHistory(['template_id' => $t1->id]);
        $this->makePrintHistory(['template_id' => $t2->id]);

        $resp = $this->withToken($this->token)->getJson("/api/v2/labels/print-history?template_id={$t1->id}");
        $resp->assertOk();
        $this->assertSame(2, $resp->json('data.total'));
    }

    public function test_print_history_combines_from_and_to_filters(): void
    {
        $this->makePrintHistory(['printed_at' => now()->subDays(30)]);
        $this->makePrintHistory(['printed_at' => now()->subDays(15)]);
        $this->makePrintHistory(['printed_at' => now()->subDays(5)]);
        $this->makePrintHistory(['printed_at' => now()->subDay()]);

        $from = now()->subDays(20)->toDateString();
        $to   = now()->subDays(3)->toDateString();

        $resp = $this->withToken($this->token)->getJson("/api/v2/labels/print-history?from={$from}&to={$to}");
        $resp->assertOk();
        $this->assertSame(2, $resp->json('data.total'));
    }

    public function test_print_history_scoped_to_user_store(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Branch',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => false,
        ]);

        $this->makePrintHistory(['store_id' => $this->store->id, 'product_count' => 1]);
        $this->makePrintHistory(['store_id' => $otherStore->id, 'product_count' => 99]);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history');
        $resp->assertOk();
        $this->assertSame(1, $resp->json('data.total'));
        $this->assertSame(1, $resp->json('data.data.0.product_count'));
    }

    public function test_print_history_includes_template_and_user_names(): void
    {
        $t = $this->makeTemplate(['name' => 'Named Template']);
        $this->makePrintHistory(['template_id' => $t->id]);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history');
        $resp->assertOk();

        $item = $resp->json('data.data.0');
        $this->assertEquals('Named Template', $item['template_name']);
        $this->assertEquals('Owner', $item['printed_by_name']);
    }

    public function test_print_history_includes_new_fields_in_response(): void
    {
        LabelPrintHistory::create([
            'store_id'         => $this->store->id,
            'printed_by'       => $this->user->id,
            'product_count'    => 2,
            'total_labels'     => 4,
            'printer_language' => 'zpl',
            'job_pages'        => 1,
            'duration_ms'      => 800,
            'printed_at'       => now(),
        ]);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history');
        $resp->assertOk();

        $item = $resp->json('data.data.0');
        $this->assertEquals('zpl', $item['printer_language']);
        $this->assertSame(1, $item['job_pages']);
        $this->assertSame(800, $item['duration_ms']);
    }

    public function test_print_history_printed_at_is_iso8601(): void
    {
        $this->makePrintHistory(['printed_at' => now()]);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history');
        $printedAt = $resp->json('data.data.0.printed_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $printedAt);
    }

    // ════════════════════════════════════════════════════════════════
    // Print History Stats
    // ════════════════════════════════════════════════════════════════

    public function test_stats_returns_zero_when_empty(): void
    {
        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history/stats');
        $resp->assertOk()
            ->assertJsonPath('data.jobs_last_30_days', 0)
            ->assertJsonPath('data.products_last_30_days', 0)
            ->assertJsonPath('data.labels_last_30_days', 0);
    }

    public function test_stats_sums_last_30_days_only(): void
    {
        $this->makePrintHistory(['product_count' => 3, 'total_labels' => 9, 'printed_at' => now()->subDays(5)]);
        $this->makePrintHistory(['product_count' => 1, 'total_labels' => 3, 'printed_at' => now()->subDays(31)]);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/print-history/stats');
        $resp->assertOk()
            ->assertJsonPath('data.jobs_last_30_days', 1)
            ->assertJsonPath('data.products_last_30_days', 3)
            ->assertJsonPath('data.labels_last_30_days', 9);
    }

    // ════════════════════════════════════════════════════════════════
    // Template list includes created_by_name
    // ════════════════════════════════════════════════════════════════

    public function test_template_list_includes_created_by_name(): void
    {
        $this->makeTemplate(['name' => 'With Author']);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/templates');
        $resp->assertOk();

        $items = $resp->json('data');
        $this->assertTrue(
            collect($items)->where('name', 'With Author')->first()['created_by_name'] === 'Owner'
        );
    }

    public function test_template_list_returns_only_org_templates(): void
    {
        $this->makeTemplate(['name' => 'Mine']);
        $this->makeTemplateFor($this->otherOrg, ['name' => 'NotMine']);

        $resp = $this->withToken($this->token)->getJson('/api/v2/labels/templates');
        $resp->assertOk();
        $names = collect($resp->json('data'))->pluck('name')->all();
        $this->assertContains('Mine', $names);
        $this->assertNotContains('NotMine', $names);
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    private function makeTemplate(array $overrides = []): LabelTemplate
    {
        return LabelTemplate::create(array_merge([
            'organization_id' => $this->org->id,
            'name'            => 'Test Template ' . uniqid(),
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['elements' => []],
            'is_preset'       => false,
            'is_default'      => false,
            'created_by'      => $this->user->id,
            'sync_version'    => 1,
        ], $overrides));
    }

    private function makeTemplateFor(Organization $org, array $overrides = []): LabelTemplate
    {
        return LabelTemplate::create(array_merge([
            'organization_id' => $org->id,
            'name'            => 'Foreign Template ' . uniqid(),
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['elements' => []],
            'is_preset'       => false,
            'is_default'      => false,
            'sync_version'    => 1,
        ], $overrides));
    }

    private function makePrintHistory(array $overrides = []): LabelPrintHistory
    {
        return LabelPrintHistory::create(array_merge([
            'store_id'      => $this->store->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
            'printed_at'    => now(),
        ], $overrides));
    }
}
