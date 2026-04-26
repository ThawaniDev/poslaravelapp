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
 * Extended test suite for the Barcode Label Printing API.
 * Covers: IDOR protection, duplicate endpoint, set-default endpoint,
 * resource enrichment (template_name, printed_by_name),
 * nullable template_id on recordPrint, pagination, edge cases.
 */
class LabelAdvancedApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    // A second org/user to verify IDOR isolation
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
            'name'          => 'Owner',
            'email'         => 'owner@labels-adv.com',
            'password_hash' => bcrypt('pw'),
            'store_id'      => $this->store->id,
            'organization_id' => $this->org->id,
            'role'          => 'owner',
            'is_active'     => true,
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
            'name'          => 'Other Owner',
            'email'         => 'other@labels-adv.com',
            'password_hash' => bcrypt('pw'),
            'store_id'      => $this->otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role'          => 'owner',
            'is_active'     => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('t2')->plainTextToken;

        // Seed active subscription with feature enabled for both orgs.
        $plan = SubscriptionPlan::create([
            'name'          => 'Adv Plan',
            'slug'          => 'adv-plan',
            'monthly_price' => 0,
            'is_active'     => true,
            'sort_order'    => 99,
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

    // ─── IDOR Protection ─────────────────────────────────────

    public function test_show_cannot_access_other_org_template(): void
    {
        $otherTemplate = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Secret',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        // Main user tries to view other org's template by ID
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/labels/templates/{$otherTemplate->id}");

        $response->assertStatus(404);
    }

    public function test_update_cannot_modify_other_org_template(): void
    {
        $otherTemplate = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$otherTemplate->id}", [
                'name' => 'Hijacked',
            ]);

        $response->assertStatus(404);
        $otherTemplate->refresh();
        $this->assertEquals('Other Template', $otherTemplate->name);
    }

    public function test_delete_cannot_remove_other_org_template(): void
    {
        $otherTemplate = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other To Delete',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/labels/templates/{$otherTemplate->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('label_templates', ['id' => $otherTemplate->id]);
    }

    public function test_duplicate_cannot_copy_other_org_template(): void
    {
        $otherTemplate = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other To Copy',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$otherTemplate->id}/duplicate");

        $response->assertStatus(404);
    }

    // ─── Duplicate Endpoint ──────────────────────────────────

    public function test_can_duplicate_template(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'My Label',
            'label_width_mm'  => 60,
            'label_height_mm' => 40,
            'layout_json'     => ['elements' => [['type' => 'barcode', 'x' => 2, 'y' => 2]]],
            'created_by'      => $this->user->id,
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$template->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My Label (Copy)')
            ->assertJsonPath('data.organization_id', $this->org->id)
            ->assertJsonPath('data.is_preset', false)
            ->assertJsonPath('data.is_default', false);

        $this->assertNotEquals($template->id, $response->json('data.id'));
        $this->assertEquals(60.0, $response->json('data.label_width_mm'));
    }

    public function test_duplicate_preserves_layout_json(): void
    {
        $layout = ['elements' => [['type' => 'product_name', 'x' => 2, 'y' => 2, 'width' => 46, 'height' => 5]]];
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'Original',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => $layout,
            'created_by'      => $this->user->id,
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$template->id}/duplicate");

        $response->assertStatus(201);
        $copy = LabelTemplate::find($response->json('data.id'));
        $this->assertEquals($layout, $copy->layout_json);
    }

    public function test_cannot_duplicate_preset_from_other_org(): void
    {
        $otherPreset = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other Preset',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'is_preset'       => true,
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$otherPreset->id}/duplicate");

        $response->assertStatus(404);
    }

    // ─── Set Default Endpoint ────────────────────────────────

    public function test_can_set_template_as_default(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'New Default',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'is_default'      => false,
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$template->id}/set-default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_set_default_clears_existing_default(): void
    {
        $existing = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'Old Default',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'is_default'      => true,
            'sync_version'    => 1,
        ]);

        $newTemplate = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'New Default',
            'label_width_mm'  => 60,
            'label_height_mm' => 40,
            'layout_json'     => ['fields' => []],
            'is_default'      => false,
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$newTemplate->id}/set-default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', true);

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_default);
    }

    public function test_set_default_cannot_modify_other_org_template(): void
    {
        $otherTemplate = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other Org Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$otherTemplate->id}/set-default");

        $response->assertStatus(404);
    }

    public function test_set_default_does_not_affect_other_org_defaults(): void
    {
        $otherDefault = LabelTemplate::create([
            'organization_id' => $this->otherOrg->id,
            'name'            => 'Other Default',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'is_default'      => true,
            'sync_version'    => 1,
        ]);

        $myTemplate = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'My Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'is_default'      => false,
            'sync_version'    => 1,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/labels/templates/{$myTemplate->id}/set-default")
            ->assertOk();

        $otherDefault->refresh();
        $this->assertTrue((bool) $otherDefault->is_default, 'Other org\'s default must not be cleared.');
    }

    // ─── Print History Enhancements ─────────────────────────

    public function test_record_print_with_nullable_template_id(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id'   => null,
                'product_count' => 3,
                'total_labels'  => 3,
                'printer_name'  => 'Zebra GK420d',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product_count', 3)
            ->assertJsonPath('data.template_id', null);
    }

    public function test_print_history_includes_template_name_and_printed_by_name(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'Named Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        LabelPrintHistory::create([
            'store_id'      => $this->store->id,
            'template_id'   => $template->id,
            'printed_by'    => $this->user->id,
            'product_count' => 2,
            'total_labels'  => 4,
            'printed_at'    => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history');

        $response->assertOk();
        $item = $response->json('data.data.0');
        $this->assertEquals('Named Template', $item['template_name']);
        $this->assertEquals('Owner', $item['printed_by_name']);
    }

    public function test_print_history_printed_at_is_iso8601(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'T',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        LabelPrintHistory::create([
            'store_id'      => $this->store->id,
            'template_id'   => $template->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
            'printed_at'    => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history');

        $response->assertOk();
        $printedAt = $response->json('data.data.0.printed_at');
        $this->assertNotNull($printedAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $printedAt);
    }

    public function test_record_print_response_includes_template_and_user_names(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'Record Template',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/print-history', [
                'template_id'   => $template->id,
                'product_count' => 5,
                'total_labels'  => 10,
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Record Template', $response->json('data.template_name'));
        $this->assertEquals('Owner', $response->json('data.printed_by_name'));
    }

    public function test_print_history_is_scoped_to_user_store(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'T',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        // History for this org's store
        LabelPrintHistory::create([
            'store_id'      => $this->store->id,
            'template_id'   => $template->id,
            'printed_by'    => $this->user->id,
            'product_count' => 1,
            'total_labels'  => 1,
            'printed_at'    => now(),
        ]);

        // History for other org's store — must NOT appear
        LabelPrintHistory::create([
            'store_id'      => $this->otherStore->id,
            'template_id'   => null,
            'printed_by'    => $this->otherUser->id,
            'product_count' => 9,
            'total_labels'  => 9,
            'printed_at'    => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/print-history');

        $response->assertOk();
        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertEquals($this->store->id, $items[0]['store_id']);
    }

    // ─── Resource Enrichment on Templates ───────────────────

    public function test_template_list_includes_created_by_name(): void
    {
        LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'Named',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'created_by'      => $this->user->id,
            'sync_version'    => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/labels/templates');

        $response->assertOk();
        $this->assertEquals('Owner', $response->json('data.0.created_by_name'));
    }

    public function test_template_resource_created_at_is_iso8601(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'ISO8601 Test',
                'label_width_mm'  => 50,
                'label_height_mm' => 30,
                'layout_json'     => ['fields' => []],
            ]);

        $response->assertStatus(201);
        $createdAt = $response->json('data.created_at');
        $this->assertNotNull($createdAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $createdAt);
    }

    // ─── Validation Edge Cases ───────────────────────────────

    public function test_create_with_max_label_size(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Max Size',
                'label_width_mm'  => 200,
                'label_height_mm' => 150,
                'layout_json'     => ['fields' => []],
            ]);

        $response->assertStatus(201);
        $this->assertEquals(200.0, (float) $response->json('data.label_width_mm'));
        $this->assertEquals(150.0, (float) $response->json('data.label_height_mm'));
    }

    public function test_create_exceeding_max_size_fails(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/labels/templates', [
                'name'            => 'Too Big',
                'label_width_mm'  => 201,
                'label_height_mm' => 151,
                'layout_json'     => ['fields' => []],
            ]);

        $response->assertStatus(422);
    }

    public function test_update_syncs_version_incrementally(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'Versioned',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 3,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/labels/templates/{$template->id}", ['name' => 'Versioned Updated']);

        $response->assertOk()
            ->assertJsonPath('data.sync_version', 4);
    }

    // ─── Auth Edge Cases ─────────────────────────────────────

    public function test_duplicate_requires_auth(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'T',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->postJson("/api/v2/labels/templates/{$template->id}/duplicate");
        $response->assertStatus(401);
    }

    public function test_set_default_requires_auth(): void
    {
        $template = LabelTemplate::create([
            'organization_id' => $this->org->id,
            'name'            => 'T',
            'label_width_mm'  => 50,
            'label_height_mm' => 30,
            'layout_json'     => ['fields' => []],
            'sync_version'    => 1,
        ]);

        $response = $this->postJson("/api/v2/labels/templates/{$template->id}/set-default");
        $response->assertStatus(401);
    }
}
