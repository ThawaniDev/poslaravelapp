<?php

namespace Tests\Feature\Industry;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PharmacyApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        $org = Organization::create(['name' => 'Pharma Org', 'slug' => 'pharma-org-' . uniqid(), 'is_active' => true]);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Pharma Store', 'slug' => 'pharma-store-' . uniqid(), 'is_active' => true]);
        $this->storeId = $store->id;
        $user = User::create(['name' => 'Pharmacist', 'email' => 'pharma-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $store->id]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;

        $otherStore = Store::create(['organization_id' => $org->id, 'name' => 'Other Pharma', 'slug' => 'other-pharma-' . uniqid(), 'is_active' => true]);
        $otherUser = User::create(['name' => 'Other Pharmacist', 'email' => 'other-pharma-' . uniqid() . '@test.com', 'password_hash' => bcrypt('password'), 'store_id' => $otherStore->id]);
        $this->otherToken = $otherUser->createToken('test', ['*'])->plainTextToken;
    }

    private function createTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS prescriptions CASCADE');
        DB::statement('CREATE TABLE prescriptions (id VARCHAR(36) PRIMARY KEY, store_id VARCHAR(36) NOT NULL, order_id VARCHAR(36), prescription_number VARCHAR(100) NOT NULL, patient_name VARCHAR(255) NOT NULL, patient_id VARCHAR(100), doctor_name VARCHAR(255) NOT NULL, doctor_license VARCHAR(100) NOT NULL, insurance_provider VARCHAR(255), insurance_claim_amount DECIMAL(10,2), notes TEXT, created_at TIMESTAMP, updated_at TIMESTAMP)');

        DB::statement('DROP TABLE IF EXISTS drug_schedules CASCADE');
        DB::statement('CREATE TABLE drug_schedules (id VARCHAR(36) PRIMARY KEY, product_id VARCHAR(36) NOT NULL, schedule_type VARCHAR(30) NOT NULL, active_ingredient VARCHAR(255), dosage_form VARCHAR(100), strength VARCHAR(100), manufacturer VARCHAR(255), requires_prescription BOOLEAN DEFAULT FALSE, created_at TIMESTAMP, updated_at TIMESTAMP)');
    }

    private function h(?string $token = null): array
    {
        auth()->forgetGuards();
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ── AUTHENTICATION ──────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v2/industry/pharmacy/prescriptions')->assertUnauthorized();
    }

    // ── PRESCRIPTIONS ───────────────────────────────────

    public function test_list_prescriptions(): void
    {
        $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(), 'prescription_number' => 'RX-001', 'patient_name' => 'John', 'doctor_name' => 'Dr Smith', 'doctor_license' => 'LIC-001',
        ], $this->h());

        $this->getJson('/api/v2/industry/pharmacy/prescriptions', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_create_prescription(): void
    {
        $res = $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(),
            'prescription_number' => 'RX-100',
            'patient_name' => 'Ahmed Al-Said',
            'patient_id' => 'PAT-100',
            'doctor_name' => 'Dr. Fatima',
            'doctor_license' => 'MOH-5678',
            'insurance_provider' => 'Al Ahlia',
            'insurance_claim_amount' => 45.00,
            'notes' => 'Chronic medication refill',
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.patient_name', 'Ahmed Al-Said');
    }

    public function test_create_prescription_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/pharmacy/prescriptions', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id', 'prescription_number', 'patient_name', 'doctor_name', 'doctor_license']);
    }

    public function test_update_prescription(): void
    {
        $create = $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(), 'prescription_number' => 'RX-200', 'patient_name' => 'Jane', 'doctor_name' => 'Dr Kim', 'doctor_license' => 'LIC-200',
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/pharmacy/prescriptions/{$id}", [
            'insurance_provider' => 'National Health', 'notes' => 'Updated notes',
        ], $this->h());
        $res->assertOk();
    }

    public function test_cannot_update_prescription_from_other_store(): void
    {
        $create = $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(), 'prescription_number' => 'RX-300', 'patient_name' => 'Bob', 'doctor_name' => 'Dr Lee', 'doctor_license' => 'LIC-300',
        ], $this->h());
        $id = $create->json('data.id');

        $this->putJson("/api/v2/industry/pharmacy/prescriptions/{$id}", ['notes' => 'Hijacked'], $this->h($this->otherToken))
            ->assertNotFound();
    }

    public function test_filter_prescriptions_by_search(): void
    {
        $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(), 'prescription_number' => 'RX-400', 'patient_name' => 'Ahmed Specific', 'doctor_name' => 'Dr X', 'doctor_license' => 'L-400',
        ], $this->h());
        $this->postJson('/api/v2/industry/pharmacy/prescriptions', [
            'order_id' => fake()->uuid(), 'prescription_number' => 'RX-401', 'patient_name' => 'Fatima Other', 'doctor_name' => 'Dr Y', 'doctor_license' => 'L-401',
        ], $this->h());

        $this->getJson('/api/v2/industry/pharmacy/prescriptions?search=Ahmed', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    // ── DRUG SCHEDULES ──────────────────────────────────

    public function test_list_drug_schedules(): void
    {
        $this->postJson('/api/v2/industry/pharmacy/drug-schedules', [
            'product_id' => fake()->uuid(), 'schedule_type' => 'otc', 'active_ingredient' => 'Ibuprofen', 'dosage_form' => 'tablet', 'strength' => '200mg', 'requires_prescription' => false,
        ], $this->h());

        $this->getJson('/api/v2/industry/pharmacy/drug-schedules', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_create_drug_schedule(): void
    {
        $res = $this->postJson('/api/v2/industry/pharmacy/drug-schedules', [
            'product_id' => fake()->uuid(),
            'schedule_type' => 'controlled',
            'active_ingredient' => 'Codeine',
            'dosage_form' => 'tablet',
            'strength' => '30mg',
            'manufacturer' => 'PharmaCo',
            'requires_prescription' => true,
        ], $this->h());
        $res->assertCreated()->assertJsonPath('data.schedule_type', 'controlled');
    }

    public function test_create_drug_schedule_requires_fields(): void
    {
        $this->postJson('/api/v2/industry/pharmacy/drug-schedules', [], $this->h())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'schedule_type', 'active_ingredient', 'dosage_form', 'strength', 'requires_prescription']);
    }

    public function test_update_drug_schedule(): void
    {
        $create = $this->postJson('/api/v2/industry/pharmacy/drug-schedules', [
            'product_id' => fake()->uuid(), 'schedule_type' => 'otc', 'active_ingredient' => 'Paracetamol', 'dosage_form' => 'tablet', 'strength' => '500mg', 'requires_prescription' => false,
        ], $this->h());
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v2/industry/pharmacy/drug-schedules/{$id}", [
            'schedule_type' => 'prescription_only', 'requires_prescription' => true,
        ], $this->h());
        $res->assertOk();
    }

    public function test_filter_drug_schedules_by_type(): void
    {
        $this->postJson('/api/v2/industry/pharmacy/drug-schedules', ['product_id' => fake()->uuid(), 'schedule_type' => 'controlled', 'active_ingredient' => 'Codeine', 'dosage_form' => 'tablet', 'strength' => '30mg', 'requires_prescription' => true], $this->h());
        $this->postJson('/api/v2/industry/pharmacy/drug-schedules', ['product_id' => fake()->uuid(), 'schedule_type' => 'otc', 'active_ingredient' => 'Aspirin', 'dosage_form' => 'tablet', 'strength' => '100mg', 'requires_prescription' => false], $this->h());

        $this->getJson('/api/v2/industry/pharmacy/drug-schedules?schedule_type=controlled', $this->h())
            ->assertOk()->assertJsonCount(1, 'data.data');
    }
}
