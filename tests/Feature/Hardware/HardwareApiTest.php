<?php

namespace Tests\Feature\Hardware;

use App\Domain\Hardware\Models\HardwareConfiguration;
use App\Domain\Hardware\Models\HardwareEventLog;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\SystemConfig\Models\CertifiedHardware;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HardwareApiTest extends TestCase
{
    use RefreshDatabase;

    private string $storeId;
    private string $token;
    private string $terminalId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create SQLite tables if they don't exist
        if (!\Schema::hasTable('hardware_configurations')) {
            \Schema::create('hardware_configurations', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('terminal_id');
                $t->string('device_type');
                $t->string('connection_type');
                $t->string('device_name')->nullable();
                $t->json('config_json')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!\Schema::hasTable('hardware_event_log')) {
            \Schema::create('hardware_event_log', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('terminal_id');
                $t->string('device_type');
                $t->string('event');
                $t->text('details')->nullable();
                $t->timestamps();
            });
        }

        if (!\Schema::hasTable('certified_hardware')) {
            \Schema::create('certified_hardware', function ($t) {
                $t->uuid('id')->primary();
                $t->string('device_type');
                $t->string('brand');
                $t->string('model');
                $t->string('driver_protocol')->nullable();
                $t->json('connection_types')->nullable();
                $t->string('firmware_version_min')->nullable();
                $t->json('paper_widths')->nullable();
                $t->text('setup_instructions')->nullable();
                $t->text('setup_instructions_ar')->nullable();
                $t->boolean('is_certified')->default(true);
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        $org = Organization::create([
            'name' => 'Hardware Test Org',
            'slug' => 'hardware-test-' . Str::random(5),
            'is_active' => true,
        ]);

        $this->storeId = Str::uuid()->toString();
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Hardware Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Hardware Tester',
            'email' => 'hw-test-' . Str::random(5) . '@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->storeId,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $user->createToken('test', ['*'])->plainTextToken;
        $this->terminalId = Str::uuid()->toString();
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ── Authentication ───────────────────────────────────────

    public function test_requires_auth_for_list_configs(): void
    {
        $response = $this->getJson('/api/v2/hardware/config');
        $response->assertStatus(401);
    }

    public function test_requires_auth_for_save_config(): void
    {
        $response = $this->postJson('/api/v2/hardware/config', []);
        $response->assertStatus(401);
    }

    public function test_requires_auth_for_event_logs(): void
    {
        $response = $this->getJson('/api/v2/hardware/event-logs');
        $response->assertStatus(401);
    }

    // ── Save Config ──────────────────────────────────────────

    public function test_save_config_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v2/hardware/config', [], $this->authHeader());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terminal_id', 'device_type', 'connection_type']);
    }

    public function test_save_config_validates_enum_values(): void
    {
        $response = $this->postJson('/api/v2/hardware/config', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'invalid_type',
            'connection_type' => 'invalid_conn',
        ], $this->authHeader());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['device_type', 'connection_type']);
    }

    public function test_save_config_creates_new_config(): void
    {
        $response = $this->postJson('/api/v2/hardware/config', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'connection_type' => 'usb',
            'device_name' => 'Epson TM-T88V',
            'config_json' => ['paper_width' => 80, 'dpi' => 203],
        ], $this->authHeader());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.device_type', 'receipt_printer');
        $response->assertJsonPath('data.connection_type', 'usb');
        $response->assertJsonPath('data.device_name', 'Epson TM-T88V');

        $this->assertDatabaseHas('hardware_configurations', [
            'store_id' => $this->storeId,
            'device_type' => 'receipt_printer',
        ]);
    }

    public function test_save_config_updates_existing(): void
    {
        // Create initial config
        $this->postJson('/api/v2/hardware/config', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'barcode_scanner',
            'connection_type' => 'usb',
            'device_name' => 'Scanner V1',
        ], $this->authHeader());

        // Update same device type for same terminal
        $response = $this->postJson('/api/v2/hardware/config', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'barcode_scanner',
            'connection_type' => 'bluetooth',
            'device_name' => 'Scanner V2',
        ], $this->authHeader());

        $response->assertStatus(200);
        $response->assertJsonPath('data.connection_type', 'bluetooth');
        $response->assertJsonPath('data.device_name', 'Scanner V2');

        // Should only have 1 config for this device type
        $count = HardwareConfiguration::where('store_id', $this->storeId)
            ->where('device_type', 'barcode_scanner')
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_save_config_records_event_log(): void
    {
        $this->postJson('/api/v2/hardware/config', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'cash_drawer',
            'connection_type' => 'usb',
        ], $this->authHeader());

        $this->assertDatabaseHas('hardware_event_log', [
            'store_id' => $this->storeId,
            'device_type' => 'cash_drawer',
            'event' => 'configured',
        ]);
    }

    // ── List Configs ─────────────────────────────────────────

    public function test_list_configs_returns_all_for_store(): void
    {
        HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'connection_type' => 'usb',
            'device_name' => 'Printer A',
            'is_active' => true,
        ]);
        HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'barcode_scanner',
            'connection_type' => 'bluetooth',
            'device_name' => 'Scanner B',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/hardware/config', $this->authHeader());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_configs_filters_by_terminal(): void
    {
        $otherTerminal = Str::uuid()->toString();

        HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'connection_type' => 'usb',
            'is_active' => true,
        ]);
        HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $otherTerminal,
            'device_type' => 'receipt_printer',
            'connection_type' => 'network',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/hardware/config?terminal_id=' . $this->terminalId, $this->authHeader());
        $response->assertJsonCount(1, 'data');
    }

    public function test_list_configs_filters_by_device_type(): void
    {
        HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'connection_type' => 'usb',
            'is_active' => true,
        ]);
        HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'cash_drawer',
            'connection_type' => 'usb',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/hardware/config?device_type=cash_drawer', $this->authHeader());
        $response->assertJsonCount(1, 'data');
    }

    // ── Remove Config ────────────────────────────────────────

    public function test_remove_config_success(): void
    {
        $config = HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'nfc_reader',
            'connection_type' => 'usb',
            'is_active' => true,
        ]);

        $response = $this->deleteJson('/api/v2/hardware/config/' . $config->id, [], $this->authHeader());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseMissing('hardware_configurations', ['id' => $config->id]);
    }

    public function test_remove_config_records_removal_event(): void
    {
        $config = HardwareConfiguration::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'customer_display',
            'connection_type' => 'serial',
            'device_name' => 'Pole Display',
            'is_active' => true,
        ]);

        $this->deleteJson('/api/v2/hardware/config/' . $config->id, [], $this->authHeader());

        $this->assertDatabaseHas('hardware_event_log', [
            'store_id' => $this->storeId,
            'device_type' => 'customer_display',
            'event' => 'removed',
        ]);
    }

    public function test_remove_config_not_found(): void
    {
        $response = $this->deleteJson('/api/v2/hardware/config/' . Str::uuid(), [], $this->authHeader());
        $response->assertStatus(404);
    }

    // ── Supported Models ─────────────────────────────────────

    public function test_supported_models_returns_active(): void
    {
        CertifiedHardware::create([
            'device_type' => 'receipt_printer',
            'brand' => 'Epson',
            'model' => 'TM-T88V',
            'is_certified' => true,
            'is_active' => true,
        ]);
        CertifiedHardware::create([
            'device_type' => 'barcode_scanner',
            'brand' => 'Zebra',
            'model' => 'DS2208',
            'is_certified' => true,
            'is_active' => false, // inactive – should be excluded
        ]);

        $response = $this->getJson('/api/v2/hardware/supported-models', $this->authHeader());
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.brand', 'Epson');
    }

    public function test_supported_models_filters_by_device_type(): void
    {
        CertifiedHardware::create([
            'device_type' => 'receipt_printer',
            'brand' => 'Epson',
            'model' => 'TM-T88V',
            'is_certified' => true,
            'is_active' => true,
        ]);
        CertifiedHardware::create([
            'device_type' => 'barcode_scanner',
            'brand' => 'Zebra',
            'model' => 'DS2208',
            'is_certified' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v2/hardware/supported-models?device_type=receipt_printer', $this->authHeader());
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.model', 'TM-T88V');
    }

    // ── Test Device ──────────────────────────────────────────

    public function test_test_device_validates_fields(): void
    {
        $response = $this->postJson('/api/v2/hardware/test', [], $this->authHeader());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terminal_id', 'device_type', 'connection_type']);
    }

    public function test_test_device_success(): void
    {
        $response = $this->postJson('/api/v2/hardware/test', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'connection_type' => 'usb',
            'test_type' => 'print',
        ], $this->authHeader());

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
        $response->assertJsonPath('data.device_type', 'receipt_printer');
        $response->assertJsonPath('data.connection_type', 'usb');
        $this->assertNotNull($response->json('data.tested_at'));

        $this->assertDatabaseHas('hardware_event_log', [
            'store_id' => $this->storeId,
            'device_type' => 'receipt_printer',
            'event' => 'test_passed',
        ]);
    }

    // ── Record Event ─────────────────────────────────────────

    public function test_record_event_validates_fields(): void
    {
        $response = $this->postJson('/api/v2/hardware/event-log', [], $this->authHeader());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terminal_id', 'device_type', 'event']);
    }

    public function test_record_event_success(): void
    {
        $response = $this->postJson('/api/v2/hardware/event-log', [
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'event' => 'paper_out',
            'details' => ['error_code' => 'E001'],
        ], $this->authHeader());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('hardware_event_log', [
            'store_id' => $this->storeId,
            'event' => 'paper_out',
        ]);
    }

    // ── Event Logs ───────────────────────────────────────────

    public function test_event_logs_returns_paginated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            HardwareEventLog::create([
                'store_id' => $this->storeId,
                'terminal_id' => $this->terminalId,
                'device_type' => 'receipt_printer',
                'event' => 'connected',
            ]);
        }

        $response = $this->getJson('/api/v2/hardware/event-logs?per_page=3', $this->authHeader());
        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 5);
        $response->assertJsonPath('data.current_page', 1);
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_event_logs_filters_by_device_type(): void
    {
        HardwareEventLog::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'event' => 'connected',
        ]);
        HardwareEventLog::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'barcode_scanner',
            'event' => 'connected',
        ]);

        $response = $this->getJson('/api/v2/hardware/event-logs?device_type=barcode_scanner', $this->authHeader());
        $response->assertJsonPath('data.total', 1);
    }

    public function test_event_logs_filters_by_event(): void
    {
        HardwareEventLog::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'event' => 'connected',
        ]);
        HardwareEventLog::create([
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'device_type' => 'receipt_printer',
            'event' => 'disconnected',
        ]);

        $response = $this->getJson('/api/v2/hardware/event-logs?event=disconnected', $this->authHeader());
        $response->assertJsonPath('data.total', 1);
    }

    // ── Data Isolation ───────────────────────────────────────

    public function test_configs_are_isolated_per_store(): void
    {
        // Create another store
        $org2 = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org-' . Str::random(5),
            'is_active' => true,
        ]);
        $otherStore = Store::create([
            'organization_id' => $org2->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        HardwareConfiguration::create([
            'store_id' => $otherStore->id,
            'terminal_id' => Str::uuid()->toString(),
            'device_type' => 'receipt_printer',
            'connection_type' => 'network',
            'is_active' => true,
        ]);

        // Our store should see 0 configs
        $response = $this->getJson('/api/v2/hardware/config', $this->authHeader());
        $response->assertJsonCount(0, 'data');
    }

    public function test_cannot_remove_other_stores_config(): void
    {
        $org2 = Organization::create([
            'name' => 'Other Org 2',
            'slug' => 'other-org2-' . Str::random(5),
            'is_active' => true,
        ]);
        $otherStore = Store::create([
            'organization_id' => $org2->id,
            'name' => 'Other Store 2',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $config = HardwareConfiguration::create([
            'store_id' => $otherStore->id,
            'terminal_id' => Str::uuid()->toString(),
            'device_type' => 'barcode_scanner',
            'connection_type' => 'bluetooth',
            'is_active' => true,
        ]);

        $response = $this->deleteJson('/api/v2/hardware/config/' . $config->id, [], $this->authHeader());
        $response->assertStatus(404);

        // Config should still exist
        $this->assertDatabaseHas('hardware_configurations', ['id' => $config->id]);
    }
}
