<?php

namespace Tests\Feature\Label;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelApiTest extends TestCase
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
}
