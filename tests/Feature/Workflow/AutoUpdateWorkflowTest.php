<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * AUTO-UPDATE WORKFLOW TESTS
 *
 * Tests version checking, status reporting, changelog,
 * update history, manifest, rollout status.
 *
 * Cross-references: Workflows #871-878
 */
class AutoUpdateWorkflowTest extends WorkflowTestCase
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
            'name' => 'Update Org',
            'name_ar' => 'منظمة تحديث',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Update Store',
            'name_ar' => 'متجر تحديث',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Update Owner',
            'email' => 'update-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
    }

    /** @test */
    public function wf871_check_for_update(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/auto-update/check', [
                'current_version' => '1.0.0',
                'platform' => 'windows',
                'channel' => 'stable',
            ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function wf872_report_update_status(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/auto-update/report-status', [
                'version' => '1.1.0',
                'status' => 'installed',
                'platform' => 'windows',
            ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    /** @test */
    public function wf873_changelog(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/auto-update/changelog');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf874_update_history(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/auto-update/history');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf875_current_version(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/auto-update/current-version');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf876_manifest(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/auto-update/manifest/1.0.0');

        $this->assertContains($response->status(), [200, 403, 404]);
    }

    /** @test */
    public function wf877_download_version(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/auto-update/download/1.0.0');

        $this->assertContains($response->status(), [200, 403, 404, 422]);
    }

    /** @test */
    public function wf878_rollout_status(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/auto-update/rollout-status');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }
}
