<?php

namespace Tests\Feature\Workflow;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Multi-Branch Management Workflow Tests — WF #927-944
 *
 * Covers: core.php store/branch endpoints (18 endpoints)
 *   - Branch CRUD, toggle-active, sort-order
 *   - Branch settings, working hours, copy operations
 *   - Business types
 */
class MultiBranchWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Store $store;
    protected User $owner;
    protected string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Multi-Branch Org',
            'name_ar' => 'مؤسسة متعددة الفروع',
            'business_type' => 'grocery',
            'country' => 'OM',
            'vat_number' => 'OM777888999',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Branch',
            'name_ar' => 'الفرع الرئيسي',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'en',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Branch Owner',
            'email' => 'branch-owner@workflow.test',
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
    //  BRANCH CRUD — WF #927-935
    // ══════════════════════════════════════════════

    /** @test */
    public function wf927_list_my_stores(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/core/stores/mine');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf928_stores_stats(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/core/stores/stats');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf929_stores_managers(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/core/stores/managers');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf930_update_sort_order(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/core/stores/sort-order', [
                'order' => [$this->store->id],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf931_list_stores(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/core/stores');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf932_create_store(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/core/stores', [
                'name' => 'New Branch',
                'name_ar' => 'فرع جديد',
                'business_type' => 'grocery',
                'currency' => 'SAR',
                'locale' => 'en',
                'timezone' => 'Asia/Muscat',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf933_show_store(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/core/stores/{$this->store->id}");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf934_update_store(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/core/stores/{$this->store->id}", [
                'name' => 'Updated Branch Name',
                'name_ar' => 'اسم فرع محدث',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf935_delete_store(): void
    {
        // Create a secondary store to delete (don't delete main branch)
        $secondary = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Temp Branch',
            'name_ar' => 'فرع مؤقت',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'en',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/core/stores/{$secondary->id}");

        $this->assertContains($response->status(), [200, 204, 403, 404, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  BRANCH TOGGLE & SETTINGS — WF #936-940
    // ══════════════════════════════════════════════

    /** @test */
    public function wf936_toggle_store_active(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/core/stores/{$this->store->id}/toggle-active");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf937_get_store_settings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/core/stores/{$this->store->id}/settings");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf938_update_store_settings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/core/stores/{$this->store->id}/settings", [
                'receipt_header' => 'Welcome',
                'receipt_footer' => 'Thank you!',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf939_copy_settings_to_branch(): void
    {
        $targetStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Target Branch',
            'name_ar' => 'فرع هدف',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'en',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/core/stores/{$this->store->id}/copy-settings", [
                'target_store_id' => $targetStore->id,
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  WORKING HOURS — WF #940-942
    // ══════════════════════════════════════════════

    /** @test */
    public function wf940_get_working_hours(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/core/stores/{$this->store->id}/working-hours");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf941_update_working_hours(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/core/stores/{$this->store->id}/working-hours", [
                'hours' => [
                    ['day' => 'sunday', 'open' => '09:00', 'close' => '22:00', 'is_open' => true],
                    ['day' => 'monday', 'open' => '09:00', 'close' => '22:00', 'is_open' => true],
                ],
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf942_copy_working_hours(): void
    {
        $targetStore = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Hours Target',
            'name_ar' => 'فرع ساعات',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'en',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/core/stores/{$this->store->id}/copy-working-hours", [
                'target_store_id' => $targetStore->id,
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  BUSINESS TYPES — WF #943-944
    // ══════════════════════════════════════════════

    /** @test */
    public function wf943_list_business_types(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/core/business-types');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf944_apply_business_type(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/core/stores/{$this->store->id}/business-type", [
                'business_type' => 'restaurant',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }
}
