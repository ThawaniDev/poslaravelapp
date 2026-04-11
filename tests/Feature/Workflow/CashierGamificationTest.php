<?php

namespace Tests\Feature\Workflow;

use App\Domain\CashierGamification\Enums\AnomalySeverity;
use App\Domain\CashierGamification\Enums\AnomalyType;
use App\Domain\CashierGamification\Enums\CashierBadgeTrigger;
use App\Domain\CashierGamification\Enums\PerformancePeriod;
use App\Domain\CashierGamification\Enums\RiskLevel;
use App\Domain\CashierGamification\Models\CashierAnomaly;
use App\Domain\CashierGamification\Models\CashierBadge;
use App\Domain\CashierGamification\Models\CashierBadgeAward;
use App\Domain\CashierGamification\Models\CashierGamificationSetting;
use App\Domain\CashierGamification\Models\CashierPerformanceSnapshot;
use App\Domain\CashierGamification\Models\CashierShiftReport;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CashierGamificationTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private User $cashier2;
    private string $storeId;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Gamification Test Org',
            'name_ar' => 'منظمة اختبار',
            'business_type' => 'grocery',
            'country' => 'SA',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Gamification Store',
            'name_ar' => 'متجر اختبار',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->storeId = $this->store->id;

        $this->owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner-gamification@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->storeId,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->assignOwnerRole($this->owner, $this->storeId);

        $this->cashier = User::create([
            'name' => 'Cashier 1',
            'email' => 'cashier1-gamification@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->storeId,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->cashier2 = User::create([
            'name' => 'Cashier 2',
            'email' => 'cashier2-gamification@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->storeId,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ─── Helper: Seed snapshots ──────────────────────────────

    private function seedSnapshot(array $overrides = []): CashierPerformanceSnapshot
    {
        return CashierPerformanceSnapshot::forceCreate(array_merge([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier->id,
            'date' => now()->toDateString(),
            'period_type' => PerformancePeriod::Daily->value,
            'pos_session_id' => fake()->uuid(),
            'active_minutes' => 480,
            'total_transactions' => 50,
            'total_items_sold' => 200,
            'total_revenue' => 5000.00,
            'total_discount_given' => 100.00,
            'avg_basket_size' => 100.00,
            'items_per_minute' => 0.42,
            'avg_transaction_time_seconds' => 120,
            'void_count' => 2,
            'void_amount' => 50.00,
            'void_rate' => 0.04,
            'return_count' => 1,
            'return_amount' => 25.00,
            'discount_count' => 5,
            'discount_rate' => 0.10,
            'price_override_count' => 1,
            'no_sale_count' => 2,
            'upsell_count' => 10,
            'upsell_rate' => 0.20,
            'cash_variance' => 0.50,
            'cash_variance_absolute' => 0.50,
            'risk_score' => 15.0,
        ], $overrides));
    }

    private function seedBadges(): void
    {
        $badges = [
            ['slug' => 'sales_champion', 'name_en' => 'Sales Champion', 'name_ar' => 'بطل المبيعات', 'trigger_type' => CashierBadgeTrigger::SalesChampion->value, 'period' => PerformancePeriod::Daily->value],
            ['slug' => 'speed_star', 'name_en' => 'Speed Star', 'name_ar' => 'نجم السرعة', 'trigger_type' => CashierBadgeTrigger::SpeedStar->value, 'period' => PerformancePeriod::Daily->value],
            ['slug' => 'zero_void', 'name_en' => 'Zero Void', 'name_ar' => 'صفر إلغاء', 'trigger_type' => CashierBadgeTrigger::ZeroVoid->value, 'period' => PerformancePeriod::Shift->value],
        ];

        foreach ($badges as $i => $badge) {
            CashierBadge::forceCreate(array_merge([
                'store_id' => $this->storeId,
                'icon' => 'emoji_events',
                'color' => '#FFD700',
                'trigger_threshold' => 0,
                'is_active' => true,
                'sort_order' => $i,
            ], $badge));
        }
    }

    // ═══════════════════════════════════════════════════════════
    // §1 — LEADERBOARD
    // ═══════════════════════════════════════════════════════════

    public function test_leaderboard_returns_snapshots_sorted_by_revenue(): void
    {
        $this->seedSnapshot(['total_revenue' => 3000, 'cashier_id' => $this->cashier->id]);
        $this->seedSnapshot(['total_revenue' => 7000, 'cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard?date=' . now()->toDateString());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data');

        // First entry should be highest revenue
        $data = $response->json('data.data');
        $this->assertEquals(7000, $data[0]['total_revenue']);
        $this->assertEquals(3000, $data[1]['total_revenue']);
    }

    public function test_leaderboard_filters_by_period_type(): void
    {
        $this->seedSnapshot(['period_type' => PerformancePeriod::Daily->value]);
        $this->seedSnapshot(['period_type' => PerformancePeriod::Shift->value, 'cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard?date=' . now()->toDateString() . '&period_type=shift');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_leaderboard_sorts_by_items_per_minute(): void
    {
        $this->seedSnapshot(['items_per_minute' => 0.3, 'cashier_id' => $this->cashier->id]);
        $this->seedSnapshot(['items_per_minute' => 0.8, 'cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard?date=' . now()->toDateString() . '&sort_by=items_per_minute');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertEquals(0.8, $data[0]['items_per_minute']);
    }

    public function test_leaderboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/cashier-gamification/leaderboard');
        $response->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // §2 — CASHIER HISTORY
    // ═══════════════════════════════════════════════════════════

    public function test_cashier_history_returns_performance_records(): void
    {
        $this->seedSnapshot(['date' => now()->subDays(2)->toDateString()]);
        $this->seedSnapshot(['date' => now()->subDay()->toDateString()]);
        $this->seedSnapshot(['date' => now()->toDateString()]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/cashier/' . $this->cashier->id . '/history');

        $response->assertOk()
            ->assertJsonCount(3, 'data.data');
    }

    public function test_cashier_history_filters_by_date_range(): void
    {
        $this->seedSnapshot(['date' => now()->subDays(5)->toDateString()]);
        $this->seedSnapshot(['date' => now()->toDateString()]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/cashier/' . $this->cashier->id . '/history?date_from=' . now()->subDay()->toDateString());

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    // ═══════════════════════════════════════════════════════════
    // §3 — BADGE DEFINITIONS
    // ═══════════════════════════════════════════════════════════

    public function test_seed_badges_creates_defaults(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/badges/seed');

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.badges_seeded', 8);

        $this->assertDatabaseCount('cashier_badges', 8);
    }

    public function test_seed_badges_is_idempotent(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v2/cashier-gamification/badges/seed');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v2/cashier-gamification/badges/seed');

        $this->assertDatabaseCount('cashier_badges', 8);
    }

    public function test_list_badge_definitions(): void
    {
        $this->seedBadges();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/badges');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $badge = $response->json('data.0');
        $this->assertArrayHasKey('slug', $badge);
        $this->assertArrayHasKey('name_en', $badge);
        $this->assertArrayHasKey('name_ar', $badge);
        $this->assertArrayHasKey('trigger_type', $badge);
    }

    public function test_create_custom_badge(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/badges', [
                'slug' => 'custom_badge',
                'name_en' => 'Custom Badge',
                'name_ar' => 'شارة مخصصة',
                'trigger_type' => 'sales_champion',
                'period' => 'daily',
                'trigger_threshold' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'custom_badge')
            ->assertJsonPath('data.name_ar', 'شارة مخصصة');
    }

    public function test_update_badge(): void
    {
        $this->seedBadges();
        $badge = CashierBadge::where('store_id', $this->storeId)->first();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/cashier-gamification/badges/' . $badge->id, [
                'name_en' => 'Updated Badge',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name_en', 'Updated Badge')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_delete_badge(): void
    {
        $this->seedBadges();
        $badge = CashierBadge::where('store_id', $this->storeId)->first();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/v2/cashier-gamification/badges/' . $badge->id);

        $response->assertOk();
        $this->assertDatabaseMissing('cashier_badges', ['id' => $badge->id]);
    }

    // ═══════════════════════════════════════════════════════════
    // §4 — BADGE AWARDS
    // ═══════════════════════════════════════════════════════════

    public function test_list_badge_awards(): void
    {
        $this->seedBadges();
        $badge = CashierBadge::where('store_id', $this->storeId)->first();
        $snapshot = $this->seedSnapshot();

        CashierBadgeAward::forceCreate([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier->id,
            'badge_id' => $badge->id,
            'snapshot_id' => $snapshot->id,
            'earned_date' => now()->toDateString(),
            'period' => PerformancePeriod::Daily->value,
            'metric_value' => 5000,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/badge-awards');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_list_badge_awards_filters_by_cashier(): void
    {
        $this->seedBadges();
        $badge = CashierBadge::where('store_id', $this->storeId)->first();
        $snapshot = $this->seedSnapshot();
        $snapshot2 = $this->seedSnapshot(['cashier_id' => $this->cashier2->id]);

        CashierBadgeAward::forceCreate([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier->id,
            'badge_id' => $badge->id,
            'snapshot_id' => $snapshot->id,
            'earned_date' => now()->toDateString(),
            'period' => PerformancePeriod::Daily->value,
            'metric_value' => 5000,
            'created_at' => now(),
        ]);
        CashierBadgeAward::forceCreate([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier2->id,
            'badge_id' => $badge->id,
            'snapshot_id' => $snapshot2->id,
            'earned_date' => now()->toDateString(),
            'period' => PerformancePeriod::Daily->value,
            'metric_value' => 3000,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/badge-awards?cashier_id=' . $this->cashier->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    // ═══════════════════════════════════════════════════════════
    // §5 — ANOMALIES
    // ═══════════════════════════════════════════════════════════

    private function seedAnomaly(array $overrides = []): CashierAnomaly
    {
        $snapshot = $this->seedSnapshot();
        return CashierAnomaly::forceCreate(array_merge([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier->id,
            'snapshot_id' => $snapshot->id,
            'anomaly_type' => AnomalyType::ExcessiveVoids->value,
            'severity' => AnomalySeverity::High->value,
            'risk_score' => 65.0,
            'title_en' => 'Excessive Void Rate',
            'title_ar' => 'معدل إلغاء مرتفع',
            'description_en' => 'Void rate is significantly above store average.',
            'description_ar' => 'معدل الإلغاء أعلى بكثير من متوسط المتجر.',
            'metric_name' => 'void_rate',
            'metric_value' => 0.15,
            'store_average' => 0.04,
            'store_stddev' => 0.02,
            'z_score' => 5.5,
            'detected_date' => now()->toDateString(),
            'is_reviewed' => false,
        ], $overrides));
    }

    public function test_list_anomalies(): void
    {
        $this->seedAnomaly();
        $this->seedAnomaly(['anomaly_type' => AnomalyType::ExcessiveNoSales->value, 'cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    public function test_list_anomalies_filters_by_severity(): void
    {
        $this->seedAnomaly(['severity' => AnomalySeverity::Critical->value]);
        $this->seedAnomaly(['severity' => AnomalySeverity::Low->value, 'cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies?severity=critical');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_list_anomalies_filters_by_cashier(): void
    {
        $this->seedAnomaly();
        $this->seedAnomaly(['cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies?cashier_id=' . $this->cashier->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_review_anomaly(): void
    {
        $anomaly = $this->seedAnomaly();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/anomalies/' . $anomaly->id . '/review', [
                'review_notes' => 'Reviewed and confirmed. Training scheduled.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_reviewed', true)
            ->assertJsonPath('data.review_notes', 'Reviewed and confirmed. Training scheduled.');

        $this->assertDatabaseHas('cashier_anomalies', [
            'id' => $anomaly->id,
            'is_reviewed' => true,
            'reviewed_by' => $this->owner->id,
        ]);
    }

    public function test_anomaly_resource_includes_all_fields(): void
    {
        $this->seedAnomaly();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies');

        $response->assertOk();
        $anomaly = $response->json('data.data.0');
        $expectedFields = [
            'id', 'store_id', 'cashier_id', 'anomaly_type', 'severity',
            'risk_score', 'title_en', 'title_ar', 'description_en', 'description_ar',
            'metric_name', 'metric_value', 'store_average', 'store_stddev', 'z_score',
            'detected_date', 'is_reviewed',
        ];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $anomaly, "Missing field: {$field}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // §6 — SHIFT REPORTS
    // ═══════════════════════════════════════════════════════════

    private function seedShiftReport(array $overrides = []): CashierShiftReport
    {
        return CashierShiftReport::forceCreate(array_merge([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier->id,
            'pos_session_id' => null,
            'report_date' => now()->toDateString(),
            'total_transactions' => 50,
            'total_revenue' => 5000.00,
            'total_items' => 200,
            'items_per_minute' => 0.42,
            'avg_basket_size' => 100.00,
            'void_count' => 2,
            'void_amount' => 50.00,
            'return_count' => 1,
            'return_amount' => 25.00,
            'discount_count' => 5,
            'discount_amount' => 100.00,
            'no_sale_count' => 2,
            'price_override_count' => 1,
            'cash_variance' => 0.50,
            'upsell_count' => 10,
            'upsell_rate' => 0.20,
            'risk_score' => 15.0,
            'risk_level' => RiskLevel::Normal->value,
            'anomaly_count' => 0,
            'badges_earned' => [],
            'summary_en' => 'Shift Report summary',
            'summary_ar' => 'ملخص تقرير الوردية',
            'sent_to_owner' => false,
        ], $overrides));
    }

    public function test_list_shift_reports(): void
    {
        $this->seedShiftReport();
        $this->seedShiftReport(['cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/shift-reports');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    public function test_list_shift_reports_filters_by_cashier(): void
    {
        $this->seedShiftReport();
        $this->seedShiftReport(['cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/shift-reports?cashier_id=' . $this->cashier->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_list_shift_reports_filters_by_risk_level(): void
    {
        $this->seedShiftReport(['risk_level' => RiskLevel::Normal->value]);
        $this->seedShiftReport(['risk_level' => RiskLevel::High->value, 'risk_score' => 55, 'cashier_id' => $this->cashier2->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/shift-reports?risk_level=high');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_show_shift_report_detail(): void
    {
        $report = $this->seedShiftReport();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/shift-reports/' . $report->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.total_transactions', 50)
            ->assertJsonPath('data.risk_level', 'normal');
    }

    public function test_shift_report_resource_includes_all_fields(): void
    {
        $this->seedShiftReport();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/shift-reports');

        $report = $response->json('data.data.0');
        $expectedFields = [
            'id', 'store_id', 'cashier_id', 'report_date', 'total_transactions',
            'total_revenue', 'total_items', 'items_per_minute', 'avg_basket_size',
            'void_count', 'void_amount', 'return_count', 'return_amount',
            'discount_count', 'discount_amount', 'no_sale_count', 'price_override_count',
            'cash_variance', 'upsell_count', 'upsell_rate', 'risk_score', 'risk_level',
            'anomaly_count', 'badges_earned', 'summary_en', 'summary_ar', 'sent_to_owner',
        ];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $report, "Missing field: {$field}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // §7 — SETTINGS
    // ═══════════════════════════════════════════════════════════

    public function test_get_settings_creates_defaults(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/settings');

        $response->assertOk()
            ->assertJsonPath('data.leaderboard_enabled', true)
            ->assertJsonPath('data.badges_enabled', true)
            ->assertJsonPath('data.anomaly_detection_enabled', true)
            ->assertJsonPath('data.shift_reports_enabled', true)
            ->assertJsonPath('data.auto_generate_on_session_close', true);

        $data = $response->json('data');
        $this->assertEquals(2.0, (float) $data['anomaly_z_score_threshold']);
        $this->assertEquals(30.0, (float) $data['risk_score_void_weight']);
        $this->assertEquals(25.0, (float) $data['risk_score_no_sale_weight']);
        $this->assertEquals(25.0, (float) $data['risk_score_discount_weight']);
        $this->assertEquals(20.0, (float) $data['risk_score_price_override_weight']);

        $this->assertDatabaseCount('cashier_gamification_settings', 1);
    }

    public function test_update_settings(): void
    {
        // Create defaults first
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v2/cashier-gamification/settings');

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/cashier-gamification/settings', [
                'leaderboard_enabled' => false,
                'anomaly_z_score_threshold' => 3.0,
                'risk_score_void_weight' => 40,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.leaderboard_enabled', false);

        $data = $response->json('data');
        $this->assertEquals(3.0, (float) $data['anomaly_z_score_threshold']);
        $this->assertEquals(40.0, (float) $data['risk_score_void_weight']);
    }

    public function test_update_settings_validation(): void
    {
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v2/cashier-gamification/settings');

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/cashier-gamification/settings', [
                'anomaly_z_score_threshold' => 10, // max is 5
            ]);

        $response->assertStatus(422);
    }

    public function test_settings_resource_includes_all_fields(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/settings');

        $settings = $response->json('data');
        $expectedFields = [
            'id', 'store_id', 'leaderboard_enabled', 'badges_enabled',
            'anomaly_detection_enabled', 'shift_reports_enabled',
            'auto_generate_on_session_close', 'anomaly_z_score_threshold',
            'risk_score_void_weight', 'risk_score_no_sale_weight',
            'risk_score_discount_weight', 'risk_score_price_override_weight',
        ];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $settings, "Missing field: {$field}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // §8 — SNAPSHOT RESOURCE FIELD VALIDATION
    // ═══════════════════════════════════════════════════════════

    public function test_snapshot_resource_includes_all_fields(): void
    {
        $this->seedSnapshot();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard?date=' . now()->toDateString());

        $snapshot = $response->json('data.data.0');
        $expectedFields = [
            'id', 'store_id', 'cashier_id', 'date', 'period_type',
            'active_minutes', 'total_transactions', 'total_items_sold',
            'total_revenue', 'avg_basket_size', 'items_per_minute',
            'void_count', 'void_rate', 'return_count', 'discount_count',
            'discount_rate', 'price_override_count', 'no_sale_count',
            'upsell_count', 'upsell_rate', 'cash_variance', 'risk_score',
        ];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $snapshot, "Missing field: {$field}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // §9 — PERMISSION ENFORCEMENT
    // ═══════════════════════════════════════════════════════════

    public function test_cashier_without_permission_cannot_access_leaderboard(): void
    {
        $this->assignCashierRole($this->cashier, $this->storeId);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard');

        $response->assertForbidden();
    }

    public function test_cashier_without_permission_cannot_access_anomalies(): void
    {
        $this->assignCashierRole($this->cashier, $this->storeId);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies');

        $response->assertForbidden();
    }

    public function test_cashier_without_permission_cannot_access_settings(): void
    {
        $this->assignCashierRole($this->cashier, $this->storeId);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/settings');

        $response->assertForbidden();
    }

    public function test_user_with_view_leaderboard_permission_can_access(): void
    {
        $this->assignStoreRole($this->cashier, 'viewer', $this->storeId, [
            'cashier_performance.view_leaderboard',
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard');

        $response->assertOk();
    }

    public function test_user_with_view_badges_permission_can_access(): void
    {
        $this->assignStoreRole($this->cashier, 'viewer', $this->storeId, [
            'cashier_performance.view_badges',
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/badges');

        $response->assertOk();
    }

    public function test_user_without_manage_permission_cannot_create_badge(): void
    {
        $this->assignStoreRole($this->cashier, 'viewer', $this->storeId, [
            'cashier_performance.view_badges',
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/badges', [
                'slug' => 'test',
                'name_en' => 'Test',
                'trigger_type' => 'sales_champion',
                'period' => 'daily',
            ]);

        $response->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // §10 — ENUM TESTS
    // ═══════════════════════════════════════════════════════════

    public function test_risk_level_from_score(): void
    {
        $this->assertEquals(RiskLevel::Normal, RiskLevel::fromScore(10));
        $this->assertEquals(RiskLevel::Elevated, RiskLevel::fromScore(25));
        $this->assertEquals(RiskLevel::High, RiskLevel::fromScore(50));
        $this->assertEquals(RiskLevel::Critical, RiskLevel::fromScore(75));
        $this->assertEquals(RiskLevel::Critical, RiskLevel::fromScore(100));
        $this->assertEquals(RiskLevel::Normal, RiskLevel::fromScore(0));
    }

    public function test_performance_period_enum_values(): void
    {
        $this->assertEquals('daily', PerformancePeriod::Daily->value);
        $this->assertEquals('weekly', PerformancePeriod::Weekly->value);
        $this->assertEquals('shift', PerformancePeriod::Shift->value);
    }

    public function test_anomaly_type_enum_values(): void
    {
        $this->assertEquals('excessive_voids', AnomalyType::ExcessiveVoids->value);
        $this->assertEquals('excessive_no_sales', AnomalyType::ExcessiveNoSales->value);
        $this->assertEquals('excessive_discounts', AnomalyType::ExcessiveDiscounts->value);
        $this->assertEquals('excessive_price_overrides', AnomalyType::ExcessivePriceOverrides->value);
        $this->assertEquals('cash_variance', AnomalyType::CashVariance->value);
        $this->assertEquals('unusual_pattern', AnomalyType::UnusualPattern->value);
    }

    public function test_badge_trigger_enum_values(): void
    {
        $this->assertEquals('sales_champion', CashierBadgeTrigger::SalesChampion->value);
        $this->assertEquals('speed_star', CashierBadgeTrigger::SpeedStar->value);
        $this->assertEquals('consistency_king', CashierBadgeTrigger::ConsistencyKing->value);
        $this->assertEquals('upsell_master', CashierBadgeTrigger::UpsellMaster->value);
        $this->assertEquals('early_bird', CashierBadgeTrigger::EarlyBird->value);
        $this->assertEquals('marathon_runner', CashierBadgeTrigger::MarathonRunner->value);
        $this->assertEquals('zero_void', CashierBadgeTrigger::ZeroVoid->value);
        $this->assertEquals('customer_favorite', CashierBadgeTrigger::CustomerFavorite->value);
    }

    // ═══════════════════════════════════════════════════════════
    // §11 — MODEL RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function test_snapshot_belongs_to_cashier(): void
    {
        $snapshot = $this->seedSnapshot();
        $this->assertNotNull($snapshot->cashier);
        $this->assertEquals($this->cashier->id, $snapshot->cashier->id);
    }

    public function test_anomaly_belongs_to_cashier(): void
    {
        $anomaly = $this->seedAnomaly();
        $this->assertNotNull($anomaly->cashier);
        $this->assertEquals($this->cashier->id, $anomaly->cashier->id);
    }

    public function test_badge_award_relationships(): void
    {
        $this->seedBadges();
        $badge = CashierBadge::where('store_id', $this->storeId)->first();
        $snapshot = $this->seedSnapshot();

        $award = CashierBadgeAward::forceCreate([
            'store_id' => $this->storeId,
            'cashier_id' => $this->cashier->id,
            'badge_id' => $badge->id,
            'snapshot_id' => $snapshot->id,
            'earned_date' => now()->toDateString(),
            'period' => PerformancePeriod::Daily->value,
            'metric_value' => 5000,
            'created_at' => now(),
        ]);

        $this->assertNotNull($award->cashier);
        $this->assertNotNull($award->badge);
        $this->assertNotNull($award->snapshot);
    }

    public function test_shift_report_belongs_to_cashier(): void
    {
        $report = $this->seedShiftReport();
        $this->assertNotNull($report->cashier);
        $this->assertEquals($this->cashier->id, $report->cashier->id);
    }

    // ═══════════════════════════════════════════════════════════
    // §12 — PAGINATION
    // ═══════════════════════════════════════════════════════════

    public function test_leaderboard_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $user = User::create([
                'name' => "Cashier $i",
                'email' => "cashier-pag-$i@test.com",
                'password_hash' => bcrypt('password'),
                'store_id' => $this->storeId,
                'organization_id' => $this->org->id,
                'role' => 'cashier',
                'is_active' => true,
            ]);
            $this->seedSnapshot(['cashier_id' => $user->id, 'total_revenue' => $i * 1000]);
        }

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard?date=' . now()->toDateString() . '&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.per_page', 2);
    }

    public function test_anomalies_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedAnomaly();
        }

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.total', 5);
    }

    // ═══════════════════════════════════════════════════════════
    // §13 — GENERATE SNAPSHOT ENDPOINT
    // ═══════════════════════════════════════════════════════════

    public function test_generate_snapshot_requires_session_id(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/generate-snapshot');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'pos_session_id is required.');
    }

    public function test_generate_snapshot_returns_404_for_invalid_session(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/generate-snapshot', [
                'pos_session_id' => fake()->uuid(),
            ]);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════
    // §14 — MARK SHIFT REPORT SENT
    // ═══════════════════════════════════════════════════════════

    public function test_mark_shift_report_sent(): void
    {
        $report = $this->seedShiftReport(['sent_to_owner' => false, 'sent_at' => null]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/shift-reports/' . $report->id . '/mark-sent');

        $response->assertOk()
            ->assertJsonPath('data.sent_to_owner', true);

        $this->assertNotNull($response->json('data.sent_at'));

        // Verify DB
        $report->refresh();
        $this->assertTrue((bool) $report->sent_to_owner);
        $this->assertNotNull($report->sent_at);
    }

    public function test_mark_shift_report_sent_requires_authentication(): void
    {
        $report = $this->seedShiftReport();

        $response = $this->postJson('/api/v2/cashier-gamification/shift-reports/' . $report->id . '/mark-sent');
        $response->assertUnauthorized();
    }

    public function test_mark_shift_report_sent_returns_404_for_other_store(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Store Mark',
            'name_ar' => 'متجر آخر',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $report = CashierShiftReport::forceCreate([
            'store_id' => $otherStore->id,
            'cashier_id' => $this->cashier->id,
            'report_date' => now()->toDateString(),
            'total_transactions' => 10,
            'total_revenue' => 500,
            'total_items' => 20,
            'items_per_minute' => 0.5,
            'avg_basket_size' => 50,
            'risk_score' => 10,
            'risk_level' => RiskLevel::Normal->value,
            'anomaly_count' => 0,
            'badges_earned' => [],
            'summary_en' => 'Test',
            'summary_ar' => 'اختبار',
            'sent_to_owner' => false,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/shift-reports/' . $report->id . '/mark-sent');

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════
    // §15 — BADGE CREATION VALIDATION
    // ═══════════════════════════════════════════════════════════

    public function test_create_badge_requires_name_fields(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v2/cashier-gamification/badges', [
                // Missing required fields
                'description_en' => 'A badge without names',
            ]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('name_en', $errors);
        $this->assertArrayHasKey('name_ar', $errors);
        $this->assertArrayHasKey('slug', $errors);
        $this->assertArrayHasKey('trigger_type', $errors);
        $this->assertArrayHasKey('period', $errors);
    }

    public function test_update_badge_allows_partial_update(): void
    {
        $this->seedBadges();
        $badge = CashierBadge::where('store_id', $this->storeId)->first();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/v2/cashier-gamification/badges/' . $badge->id, [
                'name_en' => 'Updated Name Only',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name_en', 'Updated Name Only');
    }

    // ═══════════════════════════════════════════════════════════
    // §16 — CROSS-STORE ISOLATION
    // ═══════════════════════════════════════════════════════════

    public function test_leaderboard_only_shows_own_store_data(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Store',
            'name_ar' => 'متجر آخر',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => false,
        ]);
        $otherStoreId = $otherStore->id;
        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other-owner@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStoreId,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->assignOwnerRole($otherOwner, $otherStoreId);

        // Seed data for both stores
        $this->seedSnapshot();
        CashierPerformanceSnapshot::forceCreate([
            'store_id' => $otherStoreId,
            'cashier_id' => $otherOwner->id,
            'date' => now()->toDateString(),
            'period_type' => PerformancePeriod::Daily->value,
            'total_transactions' => 100,
            'total_revenue' => 99999.99,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/leaderboard?date=' . now()->toDateString());

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');

        // Ensure the other store's data is not visible
        $data = $response->json('data.data.0');
        $this->assertNotEquals(99999.99, $data['total_revenue']);
    }

    public function test_anomalies_only_shows_own_store_data(): void
    {
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Store 2',
            'name_ar' => 'متجر آخر 2',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => false,
        ]);
        $otherStoreId = $otherStore->id;
        $otherOwner = User::create([
            'name' => 'Other Owner 2',
            'email' => 'other-owner-2@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $otherStoreId,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->assignOwnerRole($otherOwner, $otherStoreId);

        $this->seedAnomaly();
        CashierAnomaly::forceCreate([
            'store_id' => $otherStoreId,
            'cashier_id' => $otherOwner->id,
            'anomaly_type' => AnomalyType::ExcessiveVoids->value,
            'severity' => AnomalySeverity::Critical->value,
            'risk_score' => 99,
            'title_en' => 'Secret',
            'title_ar' => 'سري',
            'metric_name' => 'void_rate',
            'metric_value' => 0.99,
            'detected_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v2/cashier-gamification/anomalies');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    }
}
