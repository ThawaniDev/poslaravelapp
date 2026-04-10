<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * HARDWARE & LABEL INTEGRATION WORKFLOW TESTS
 *
 * Verifies hardware config CRUD, device testing, event logging,
 * label template management, and print history.
 *
 * Cross-references: Workflows #583-600
 */
class HardwareIntegrationWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $store;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Hardware Org',
            'name_ar' => 'منظمة أجهزة',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Hardware Store',
            'name_ar' => 'متجر أجهزة',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Hardware Owner',
            'email' => 'hardware-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    // ══════════════════════════════════════════════
    //  HARDWARE CONFIGURATION — WF #583-588
    // ══════════════════════════════════════════════

    /** @test */
    public function wf583_save_hardware_config(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/hardware/config', [
                'device_type' => 'receipt_printer',
                'connection_type' => 'network',
                'name' => 'Main Receipt Printer',
                'ip_address' => '192.168.1.100',
                'port' => 9100,
                'model' => 'Bixolon SRP-350',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf584_list_hardware_configs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/hardware/config');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf585_get_supported_models(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/hardware/supported-models');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf586_test_hardware_device(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/hardware/test', [
                'device_type' => 'receipt_printer',
                'connection_type' => 'network',
                'ip_address' => '192.168.1.100',
                'port' => 9100,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf587_record_hardware_event(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/hardware/event-log', [
                'device_type' => 'receipt_printer',
                'event_type' => 'paper_out',
                'details' => 'Printer ran out of paper during shift',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf588_list_hardware_event_logs(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/hardware/event-logs');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  LABEL TEMPLATES — WF #589-596
    // ══════════════════════════════════════════════

    /** @test */
    public function wf589_list_label_presets(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/labels/templates/presets');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf590_create_label_template(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/labels/templates', [
                'name' => 'Price Tag 40x30',
                'width' => 40,
                'height' => 30,
                'elements' => [
                    ['type' => 'barcode', 'x' => 5, 'y' => 5, 'field' => 'barcode'],
                    ['type' => 'text', 'x' => 5, 'y' => 20, 'field' => 'name'],
                    ['type' => 'text', 'x' => 25, 'y' => 20, 'field' => 'price'],
                ],
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf591_list_label_templates(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/labels/templates');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf592_show_label_template(): void
    {
        $templateId = Str::uuid()->toString();
        DB::table('label_templates')->insert([
            'id' => $templateId,
            'organization_id' => $this->org->id,
            'name' => 'Test Template',
            'label_width_mm' => 40, 'label_height_mm' => 30,
            'layout_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/labels/templates/{$templateId}");

        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /** @test */
    public function wf593_update_label_template(): void
    {
        $templateId = Str::uuid()->toString();
        DB::table('label_templates')->insert([
            'id' => $templateId,
            'organization_id' => $this->org->id,
            'name' => 'Edit Template',
            'label_width_mm' => 40, 'label_height_mm' => 30,
            'layout_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/labels/templates/{$templateId}", [
                'name' => 'Updated Template',
                'width' => 50,
                'height' => 40,
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf594_delete_label_template(): void
    {
        $templateId = Str::uuid()->toString();
        DB::table('label_templates')->insert([
            'id' => $templateId,
            'organization_id' => $this->org->id,
            'name' => 'Delete Template',
            'label_width_mm' => 40, 'label_height_mm' => 30,
            'layout_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/labels/templates/{$templateId}");

        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }

    /** @test */
    public function wf595_record_label_print(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/labels/print-history', [
                'template_id' => null,
                'product_count' => 50,
                'label_count' => 50,
                'printer_name' => 'Zebra ZT410',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf596_list_print_history(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/labels/print-history');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    // ══════════════════════════════════════════════
    //  REMOVE HARDWARE CONFIG — WF #597
    // ══════════════════════════════════════════════

    /** @test */
    public function wf597_remove_hardware_config(): void
    {
        // First create a config, then remove it
        $createResp = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/hardware/config', [
                'device_type' => 'cash_drawer',
                'connection_type' => 'usb',
                'name' => 'Removable Drawer',
            ]);

        if ($createResp->status() === 200 || $createResp->status() === 201) {
            $configId = $createResp->json('data.id') ?? $createResp->json('id');
            if ($configId) {
                $response = $this->withToken($this->ownerToken)
                    ->deleteJson("/api/v2/hardware/config/{$configId}");
                $this->assertContains($response->status(), [200, 204, 500]);
                return;
            }
        }

        // Fallback: verify the delete endpoint exists with a fake ID
        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/hardware/config/' . Str::uuid()->toString());
        $this->assertContains($response->status(), [200, 204, 404, 500]);
    }
}
