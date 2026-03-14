<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\SystemConfig\Models\ABTest;
use App\Domain\SystemConfig\Models\ABTestVariant;
use App\Domain\SystemConfig\Models\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureFlagApiTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::forceCreate([
            'name' => 'P7 Test Admin',
            'email' => 'p7admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'admin-api');
    }

    // ═══════════════════════════════════════════════════════════
    //  Auth
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_feature_flags(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v2/admin/feature-flags')->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_ab_tests(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v2/admin/ab-tests')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    //  Feature Flag CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_feature_flags_empty(): void
    {
        $r = $this->getJson('/api/v2/admin/feature-flags');
        $r->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.flags', []);
    }

    public function test_create_feature_flag(): void
    {
        $r = $this->postJson('/api/v2/admin/feature-flags', [
            'flag_key' => 'new_checkout',
            'description' => 'New checkout flow',
            'is_enabled' => true,
            'rollout_percentage' => 50,
        ]);

        $r->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.flag_key', 'new_checkout')
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.rollout_percentage', 50);

        $this->assertDatabaseHas('feature_flags', ['flag_key' => 'new_checkout']);
    }

    public function test_create_feature_flag_validation_requires_key(): void
    {
        $this->postJson('/api/v2/admin/feature-flags', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flag_key']);
    }

    public function test_create_feature_flag_unique_key(): void
    {
        FeatureFlag::forceCreate(['flag_key' => 'dup_key', 'is_enabled' => false, 'rollout_percentage' => 100]);

        $this->postJson('/api/v2/admin/feature-flags', ['flag_key' => 'dup_key'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flag_key']);
    }

    public function test_show_feature_flag(): void
    {
        $flag = FeatureFlag::forceCreate([
            'flag_key' => 'show_me',
            'description' => 'Test desc',
            'is_enabled' => true,
            'rollout_percentage' => 75,
        ]);

        $this->getJson("/api/v2/admin/feature-flags/{$flag->id}")
            ->assertOk()
            ->assertJsonPath('data.flag_key', 'show_me')
            ->assertJsonPath('data.description', 'Test desc');
    }

    public function test_show_feature_flag_not_found(): void
    {
        $this->getJson('/api/v2/admin/feature-flags/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_update_feature_flag(): void
    {
        $flag = FeatureFlag::forceCreate([
            'flag_key' => 'to_update',
            'is_enabled' => false,
            'rollout_percentage' => 100,
        ]);

        $this->putJson("/api/v2/admin/feature-flags/{$flag->id}", [
            'description' => 'Updated',
            'rollout_percentage' => 30,
        ])->assertOk()
          ->assertJsonPath('data.description', 'Updated')
          ->assertJsonPath('data.rollout_percentage', 30);
    }

    public function test_delete_feature_flag(): void
    {
        $flag = FeatureFlag::forceCreate([
            'flag_key' => 'to_delete',
            'is_enabled' => false,
            'rollout_percentage' => 100,
        ]);

        $this->deleteJson("/api/v2/admin/feature-flags/{$flag->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('feature_flags', ['id' => $flag->id]);
    }

    public function test_toggle_feature_flag(): void
    {
        $flag = FeatureFlag::forceCreate([
            'flag_key' => 'toggle_me',
            'is_enabled' => false,
            'rollout_percentage' => 100,
        ]);

        $r = $this->postJson("/api/v2/admin/feature-flags/{$flag->id}/toggle");
        $r->assertOk()->assertJsonPath('data.is_enabled', true);

        // Toggle again
        $r2 = $this->postJson("/api/v2/admin/feature-flags/{$flag->id}/toggle");
        $r2->assertOk()->assertJsonPath('data.is_enabled', false);
    }

    public function test_list_feature_flags_with_data(): void
    {
        FeatureFlag::forceCreate(['flag_key' => 'alpha', 'is_enabled' => true, 'rollout_percentage' => 100]);
        FeatureFlag::forceCreate(['flag_key' => 'beta', 'is_enabled' => false, 'rollout_percentage' => 50]);
        FeatureFlag::forceCreate(['flag_key' => 'gamma', 'is_enabled' => true, 'rollout_percentage' => 25]);

        $r = $this->getJson('/api/v2/admin/feature-flags');
        $r->assertOk()->assertJsonPath('data.total', 3);
    }

    public function test_list_feature_flags_filter_by_enabled(): void
    {
        FeatureFlag::forceCreate(['flag_key' => 'on1', 'is_enabled' => true, 'rollout_percentage' => 100]);
        FeatureFlag::forceCreate(['flag_key' => 'off1', 'is_enabled' => false, 'rollout_percentage' => 100]);

        $this->getJson('/api/v2/admin/feature-flags?is_enabled=true')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_feature_flags_search(): void
    {
        FeatureFlag::forceCreate(['flag_key' => 'checkout_v2', 'description' => 'New checkout', 'is_enabled' => false, 'rollout_percentage' => 100]);
        FeatureFlag::forceCreate(['flag_key' => 'sidebar', 'description' => 'Side bar feature', 'is_enabled' => false, 'rollout_percentage' => 100]);

        $this->getJson('/api/v2/admin/feature-flags?search=checkout')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_create_flag_with_target_plans(): void
    {
        $r = $this->postJson('/api/v2/admin/feature-flags', [
            'flag_key' => 'targeted',
            'target_plan_ids' => ['plan-1', 'plan-2'],
            'target_store_ids' => ['store-a'],
        ]);

        $r->assertStatus(201);
        $flag = FeatureFlag::where('flag_key', 'targeted')->first();
        $this->assertEquals(['plan-1', 'plan-2'], $flag->target_plan_ids);
        $this->assertEquals(['store-a'], $flag->target_store_ids);
    }

    public function test_show_flag_includes_ab_tests(): void
    {
        $flag = FeatureFlag::forceCreate(['flag_key' => 'with_tests', 'is_enabled' => true, 'rollout_percentage' => 100]);
        $test = ABTest::forceCreate([
            'name' => 'Test 1',
            'feature_flag_id' => $flag->id,
            'status' => 'draft',
            'traffic_percentage' => 100,
        ]);
        ABTestVariant::forceCreate([
            'ab_test_id' => $test->id,
            'variant_key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);

        $this->getJson("/api/v2/admin/feature-flags/{$flag->id}")
            ->assertOk()
            ->assertJsonPath('data.ab_tests.0.name', 'Test 1')
            ->assertJsonPath('data.ab_tests.0.variants.0.variant_key', 'control');
    }

    // ═══════════════════════════════════════════════════════════
    //  A/B Tests CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_ab_tests_empty(): void
    {
        $this->getJson('/api/v2/admin/ab-tests')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_create_ab_test(): void
    {
        $flag = FeatureFlag::forceCreate(['flag_key' => 'ab_flag', 'is_enabled' => true, 'rollout_percentage' => 100]);

        $r = $this->postJson('/api/v2/admin/ab-tests', [
            'name' => 'Checkout Color Test',
            'description' => 'Testing button colors',
            'feature_flag_id' => $flag->id,
            'metric_key' => 'checkout_conversion',
            'traffic_percentage' => 80,
            'variants' => [
                ['variant_key' => 'control', 'variant_label' => 'Blue', 'weight' => 50, 'is_control' => true],
                ['variant_key' => 'treatment', 'variant_label' => 'Green', 'weight' => 50, 'is_control' => false],
            ],
        ]);

        $r->assertStatus(201)
            ->assertJsonPath('data.name', 'Checkout Color Test')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.traffic_percentage', 80);

        $this->assertDatabaseHas('ab_tests', ['name' => 'Checkout Color Test']);
        $this->assertDatabaseCount('ab_test_variants', 2);
    }

    public function test_create_ab_test_validation(): void
    {
        $this->postJson('/api/v2/admin/ab-tests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_ab_test_without_flag(): void
    {
        $r = $this->postJson('/api/v2/admin/ab-tests', [
            'name' => 'Standalone Test',
            'variants' => [
                ['variant_key' => 'a', 'weight' => 50],
                ['variant_key' => 'b', 'weight' => 50],
            ],
        ]);

        $r->assertStatus(201)
            ->assertJsonPath('data.feature_flag', null);
    }

    public function test_show_ab_test(): void
    {
        $test = ABTest::forceCreate([
            'name' => 'Show Test',
            'status' => 'draft',
            'traffic_percentage' => 100,
        ]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'v1', 'weight' => 50, 'is_control' => true]);

        $this->getJson("/api/v2/admin/ab-tests/{$test->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Show Test')
            ->assertJsonPath('data.variants.0.variant_key', 'v1');
    }

    public function test_show_ab_test_not_found(): void
    {
        $this->getJson('/api/v2/admin/ab-tests/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_update_ab_test(): void
    {
        $test = ABTest::forceCreate([
            'name' => 'Old Name',
            'status' => 'draft',
            'traffic_percentage' => 100,
        ]);

        $this->putJson("/api/v2/admin/ab-tests/{$test->id}", [
            'name' => 'New Name',
            'traffic_percentage' => 60,
        ])->assertOk()
          ->assertJsonPath('data.name', 'New Name')
          ->assertJsonPath('data.traffic_percentage', 60);
    }

    public function test_cannot_update_running_test(): void
    {
        $test = ABTest::forceCreate([
            'name' => 'Running',
            'status' => 'running',
            'traffic_percentage' => 100,
        ]);

        $this->putJson("/api/v2/admin/ab-tests/{$test->id}", ['name' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_delete_ab_test(): void
    {
        $test = ABTest::forceCreate([
            'name' => 'Delete Me',
            'status' => 'draft',
            'traffic_percentage' => 100,
        ]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'v1', 'weight' => 50, 'is_control' => false]);

        $this->deleteJson("/api/v2/admin/ab-tests/{$test->id}")
            ->assertOk();

        $this->assertDatabaseMissing('ab_tests', ['id' => $test->id]);
        $this->assertDatabaseCount('ab_test_variants', 0);
    }

    public function test_cannot_delete_running_test(): void
    {
        $test = ABTest::forceCreate([
            'name' => 'Running',
            'status' => 'running',
            'traffic_percentage' => 100,
        ]);

        $this->deleteJson("/api/v2/admin/ab-tests/{$test->id}")
            ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    //  A/B Test Lifecycle (start / stop / results)
    // ═══════════════════════════════════════════════════════════

    public function test_start_ab_test(): void
    {
        $test = ABTest::forceCreate(['name' => 'Start Me', 'status' => 'draft', 'traffic_percentage' => 100]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'a', 'weight' => 50, 'is_control' => true]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'b', 'weight' => 50, 'is_control' => false]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'running');

        $this->assertDatabaseHas('ab_tests', ['id' => $test->id, 'status' => 'running']);
    }

    public function test_cannot_start_test_without_enough_variants(): void
    {
        $test = ABTest::forceCreate(['name' => 'No Variants', 'status' => 'draft', 'traffic_percentage' => 100]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'only_one', 'weight' => 100, 'is_control' => true]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/start")
            ->assertStatus(422);
    }

    public function test_cannot_start_already_running_test(): void
    {
        $test = ABTest::forceCreate(['name' => 'Running', 'status' => 'running', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/start")
            ->assertStatus(422);
    }

    public function test_stop_running_test(): void
    {
        $test = ABTest::forceCreate(['name' => 'Stop Me', 'status' => 'running', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/stop")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_cannot_stop_non_running_test(): void
    {
        $test = ABTest::forceCreate(['name' => 'Draft', 'status' => 'draft', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/stop")
            ->assertStatus(422);
    }

    public function test_get_test_results(): void
    {
        $test = ABTest::forceCreate(['name' => 'Results Test', 'status' => 'completed', 'traffic_percentage' => 100]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'control', 'weight' => 50, 'is_control' => true]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'treatment', 'weight' => 50, 'is_control' => false]);

        $r = $this->getJson("/api/v2/admin/ab-tests/{$test->id}/results");
        $r->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'test',
                    'results',
                    'winner',
                    'confidence',
                ],
            ])
            ->assertJsonCount(2, 'data.results');
    }

    // ═══════════════════════════════════════════════════════════
    //  Variant Management
    // ═══════════════════════════════════════════════════════════

    public function test_add_variant(): void
    {
        $test = ABTest::forceCreate(['name' => 'Variant Test', 'status' => 'draft', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/variants", [
            'variant_key' => 'control',
            'variant_label' => 'Control Group',
            'weight' => 50,
            'is_control' => true,
        ])->assertStatus(201)
          ->assertJsonPath('data.variant_key', 'control');

        $this->assertDatabaseCount('ab_test_variants', 1);
    }

    public function test_cannot_add_duplicate_variant_key(): void
    {
        $test = ABTest::forceCreate(['name' => 'Dup Variant', 'status' => 'draft', 'traffic_percentage' => 100]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'control', 'weight' => 50, 'is_control' => true]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/variants", [
            'variant_key' => 'control',
        ])->assertStatus(422);
    }

    public function test_cannot_add_variant_to_running_test(): void
    {
        $test = ABTest::forceCreate(['name' => 'Running', 'status' => 'running', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/variants", [
            'variant_key' => 'new_one',
        ])->assertStatus(422);
    }

    public function test_remove_variant(): void
    {
        $test = ABTest::forceCreate(['name' => 'Remove Var', 'status' => 'draft', 'traffic_percentage' => 100]);
        $variant = ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'rm_me', 'weight' => 50, 'is_control' => false]);

        $this->deleteJson("/api/v2/admin/ab-tests/{$test->id}/variants/{$variant->id}")
            ->assertOk();

        $this->assertDatabaseMissing('ab_test_variants', ['id' => $variant->id]);
    }

    public function test_cannot_remove_variant_from_running_test(): void
    {
        $test = ABTest::forceCreate(['name' => 'Running', 'status' => 'running', 'traffic_percentage' => 100]);
        $variant = ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'v1', 'weight' => 50, 'is_control' => false]);

        $this->deleteJson("/api/v2/admin/ab-tests/{$test->id}/variants/{$variant->id}")
            ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    //  Filter & Pagination
    // ═══════════════════════════════════════════════════════════

    public function test_list_ab_tests_filter_by_status(): void
    {
        ABTest::forceCreate(['name' => 'Draft 1', 'status' => 'draft', 'traffic_percentage' => 100]);
        ABTest::forceCreate(['name' => 'Running 1', 'status' => 'running', 'traffic_percentage' => 100]);
        ABTest::forceCreate(['name' => 'Running 2', 'status' => 'running', 'traffic_percentage' => 100]);

        $this->getJson('/api/v2/admin/ab-tests?status=running')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_list_ab_tests_filter_by_feature_flag(): void
    {
        $flag = FeatureFlag::forceCreate(['flag_key' => 'filter_flag', 'is_enabled' => true, 'rollout_percentage' => 100]);

        ABTest::forceCreate(['name' => 'With Flag', 'feature_flag_id' => $flag->id, 'status' => 'draft', 'traffic_percentage' => 100]);
        ABTest::forceCreate(['name' => 'Without Flag', 'status' => 'draft', 'traffic_percentage' => 100]);

        $this->getJson("/api/v2/admin/ab-tests?feature_flag_id={$flag->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_list_ab_tests_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            ABTest::forceCreate(['name' => "Test {$i}", 'status' => 'draft', 'traffic_percentage' => 100]);
        }

        $r = $this->getJson('/api/v2/admin/ab-tests?per_page=5');
        $r->assertOk()
            ->assertJsonPath('data.total', 20)
            ->assertJsonPath('data.last_page', 4)
            ->assertJsonCount(5, 'data.tests');
    }

    // ═══════════════════════════════════════════════════════════
    //  Variant Metadata
    // ═══════════════════════════════════════════════════════════

    public function test_variant_with_metadata(): void
    {
        $test = ABTest::forceCreate(['name' => 'Meta Test', 'status' => 'draft', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/variants", [
            'variant_key' => 'styled',
            'metadata' => ['color' => '#FF0000', 'font_size' => 14],
        ])->assertStatus(201);

        $variant = ABTestVariant::where('variant_key', 'styled')->first();
        $this->assertEquals(['color' => '#FF0000', 'font_size' => 14], $variant->metadata);
    }

    // ═══════════════════════════════════════════════════════════
    //  Edge cases
    // ═══════════════════════════════════════════════════════════

    public function test_create_flag_with_minimum_fields(): void
    {
        $this->postJson('/api/v2/admin/feature-flags', ['flag_key' => 'minimal'])
            ->assertStatus(201)
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.rollout_percentage', 100);
    }

    public function test_update_flag_key_uniqueness(): void
    {
        FeatureFlag::forceCreate(['flag_key' => 'existing', 'is_enabled' => false, 'rollout_percentage' => 100]);
        $flag2 = FeatureFlag::forceCreate(['flag_key' => 'other', 'is_enabled' => false, 'rollout_percentage' => 100]);

        $this->putJson("/api/v2/admin/feature-flags/{$flag2->id}", ['flag_key' => 'existing'])
            ->assertUnprocessable();
    }

    public function test_rollout_percentage_validation(): void
    {
        $this->postJson('/api/v2/admin/feature-flags', [
            'flag_key' => 'bad_pct',
            'rollout_percentage' => 150,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['rollout_percentage']);
    }

    public function test_ab_test_end_date_before_start(): void
    {
        $this->postJson('/api/v2/admin/ab-tests', [
            'name' => 'Bad Dates',
            'start_date' => '2025-06-01',
            'end_date' => '2025-05-01',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['end_date']);
    }

    public function test_delete_flag_with_ab_tests_cascades(): void
    {
        $flag = FeatureFlag::forceCreate(['flag_key' => 'cascade_flag', 'is_enabled' => false, 'rollout_percentage' => 100]);
        $test = ABTest::forceCreate(['name' => 'Linked', 'feature_flag_id' => $flag->id, 'status' => 'draft', 'traffic_percentage' => 100]);

        $this->deleteJson("/api/v2/admin/feature-flags/{$flag->id}")
            ->assertOk();

        // The ab_test should have its feature_flag_id set to null (nullOnDelete)
        $this->assertNull(ABTest::find($test->id)->feature_flag_id);
    }

    public function test_start_sets_start_date_if_null(): void
    {
        $test = ABTest::forceCreate(['name' => 'Auto Date', 'status' => 'draft', 'traffic_percentage' => 100]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'a', 'weight' => 50, 'is_control' => true]);
        ABTestVariant::forceCreate(['ab_test_id' => $test->id, 'variant_key' => 'b', 'weight' => 50, 'is_control' => false]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/start")
            ->assertOk();

        $this->assertNotNull(ABTest::find($test->id)->start_date);
    }

    public function test_stop_sets_end_date(): void
    {
        $test = ABTest::forceCreate(['name' => 'End Date', 'status' => 'running', 'traffic_percentage' => 100]);

        $this->postJson("/api/v2/admin/ab-tests/{$test->id}/stop")
            ->assertOk();

        $this->assertNotNull(ABTest::find($test->id)->end_date);
    }
}
