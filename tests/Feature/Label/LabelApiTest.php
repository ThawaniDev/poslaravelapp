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

class LabelApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@label.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Seed a plan + active subscription with the label printing feature enabled.
        $this->plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'monthly_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PlanFeatureToggle::create([
            'subscription_plan_id' => $this->plan->id,
            'feature_key' => 'barcode_label_printing',
            'is_enabled' => true,
        ]);
        StoreSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    // ─── Templates ───────────────────────────────────────────

    public function test_can_list_templates(): void
    {
        LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard Label',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'is_default' => true,
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Standard Label', $data[0]['name']);
    }

    public function test_can_create_template(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name' => 'New Template',
                'label_width_mm' => 60,
                'label_height_mm' => 40,
                'layout_json' => ['fields' => [['type' => 'barcode', 'x' => 10, 'y' => 5]]],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Template')
            ->assertJsonPath('data.is_preset', false);

        $this->assertEquals(60, $response->json('data.label_width_mm'));
    }

    public function test_create_template_validates_min_size(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name' => 'Tiny',
                'label_width_mm' => 10,  // min is 20
                'label_height_mm' => 5,  // min is 15
                'layout_json' => ['fields' => []],
            ]);

        $response->assertStatus(422);
    }

    public function test_can_show_template(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Me',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/labels/templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Show Me');
    }

    public function test_can_update_template(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
        $this->assertEquals(2, $response->json('data.sync_version'));
    }

    public function test_cannot_update_preset(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'System Preset',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'is_preset' => true,
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_delete_template(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Delete Me',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/labels/templates/{$template->id}");

        $response->assertOk();
    }

    public function test_cannot_delete_preset(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'System Preset',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'is_preset' => true,
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/labels/templates/{$template->id}");

        $response->assertStatus(422);
    }

    public function test_setting_default_clears_previous(): void
    {
        $first = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'First Default',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'is_default' => true,
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name' => 'New Default',
                'label_width_mm' => 60,
                'label_height_mm' => 40,
                'layout_json' => ['fields' => []],
                'is_default' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_default', true);

        $first->refresh();
        $this->assertFalse((bool) $first->is_default);
    }

    // ─── Print History ───────────────────────────────────────

    public function test_can_record_print_history(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Template',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id' => $template->id,
                'product_count' => 5,
                'total_labels' => 10,
                'printer_name' => 'Zebra ZD420',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product_count', 5)
            ->assertJsonPath('data.total_labels', 10)
            ->assertJsonPath('data.printer_name', 'Zebra ZD420');
    }

    public function test_can_get_print_history(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Template',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        LabelPrintHistory::create([
            'store_id' => $this->store->id,
            'template_id' => $template->id,
            'printed_by' => $this->user->id,
            'product_count' => 3,
            'total_labels' => 6,
            'printed_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_requires_auth(): void
    {
        $response = $this->getJson('/api/v2/labels/templates');
        $response->assertStatus(401);
    }

    // ─── Subscription Feature Gate ───────────────────────────

    public function test_blocks_when_feature_disabled(): void
    {
        // Restore the real plan.feature middleware (TestCase aliases it to a bypass).
        app('router')->aliasMiddleware('plan.feature', \App\Http\Middleware\CheckPlanFeature::class);

        // Disable the feature on the active plan.
        PlanFeatureToggle::where('subscription_plan_id', $this->plan->id)
            ->where('feature_key', 'barcode_label_printing')
            ->update(['is_enabled' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_not_available')
            ->assertJsonPath('feature_key', 'barcode_label_printing')
            ->assertJsonPath('upgrade_required', true);
    }

    public function test_blocks_when_no_subscription(): void
    {
        app('router')->aliasMiddleware('plan.feature', \App\Http\Middleware\CheckPlanFeature::class);

        StoreSubscription::where('organization_id', $this->org->id)->delete();

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_not_available');
    }

    public function test_allows_when_feature_enabled(): void
    {
        // Sanity check that the gate passes when feature is enabled.
        app('router')->aliasMiddleware('plan.feature', \App\Http\Middleware\CheckPlanFeature::class);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates');

        $response->assertOk();
    }

    // ─── Presets ─────────────────────────────────────────────

    public function test_can_list_presets(): void
    {
        LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard Preset',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'is_preset' => true,
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);
        // A non-preset template should NOT be returned.
        LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Custom',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'is_preset' => false,
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates/presets');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Standard Preset', $data[0]['name']);
    }

    // ─── Permissions ─────────────────────────────────────────

    public function test_record_print_history_validation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'product_count' => 0, // invalid: min 1
            ]);

        $response->assertStatus(422);
    }

    public function test_print_history_returns_records_for_store_only(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name' => 'Tpl',
            'label_width_mm' => 50,
            'label_height_mm' => 30,
            'layout_json' => ['fields' => []],
            'created_by' => $this->user->id,
            'sync_version' => 1,
        ]);

        // Belongs to this store
        LabelPrintHistory::create([
            'store_id' => $this->store->id,
            'template_id' => $template->id,
            'printed_by' => $this->user->id,
            'product_count' => 1,
            'total_labels' => 1,
            'printed_at' => now(),
        ]);

        // Belongs to a different store - should NOT show
        $otherStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => false,
        ]);
        LabelPrintHistory::create([
            'store_id' => $otherStore->id,
            'template_id' => $template->id,
            'printed_by' => $this->user->id,
            'product_count' => 99,
            'total_labels' => 99,
            'printed_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history');

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['product_count']);
    }
}
