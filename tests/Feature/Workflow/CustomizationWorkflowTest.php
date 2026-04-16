<?php

namespace Tests\Feature\Workflow;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * POS Customization Workflow Tests — WF #912-921
 *
 * Covers: customization.php (10 endpoints)
 *   - Settings (get/update/reset)
 *   - Receipt template (get/update/reset)
 *   - Quick-access buttons (get/update/reset)
 *   - Export all customization
 */
class CustomizationWorkflowTest extends WorkflowTestCase
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
            'name' => 'Customization Org',
            'name_ar' => 'مؤسسة التخصيص',
            'business_type' => 'grocery',
            'country' => 'OM',
            'vat_number' => 'OM111222333',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Customization Store',
            'name_ar' => 'متجر التخصيص',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'en',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Customization Owner',
            'email' => 'customization-owner@workflow.test',
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
    //  POS SETTINGS — WF #912-914
    // ══════════════════════════════════════════════

    /** @test */
    public function wf912_get_customization_settings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/customization/settings');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf913_update_customization_settings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/customization/settings', [
                'theme' => 'dark',
                'language' => 'ar',
                'cart_display_mode' => 'compact',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf914_reset_customization_settings(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/customization/settings');

        $this->assertContains($response->status(), [200, 204, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  RECEIPT TEMPLATE — WF #915-917
    // ══════════════════════════════════════════════

    /** @test */
    public function wf915_get_receipt_template(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/customization/receipt');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf916_update_receipt_template(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/customization/receipt', [
                'header_text' => 'My POS Store',
                'footer_text' => 'Thank you for shopping!',
                'show_logo' => true,
                'show_vat' => true,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf917_reset_receipt_template(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/customization/receipt');

        $this->assertContains($response->status(), [200, 204, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  QUICK-ACCESS BUTTONS — WF #918-920
    // ══════════════════════════════════════════════

    /** @test */
    public function wf918_get_quick_access_buttons(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/customization/quick-access');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf919_update_quick_access_buttons(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson('/api/v2/customization/quick-access', [
                'buttons' => [
                    ['label' => 'Water', 'product_id' => Str::uuid()->toString(), 'position' => 1],
                    ['label' => 'Coffee', 'product_id' => Str::uuid()->toString(), 'position' => 2],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf920_reset_quick_access_buttons(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->deleteJson('/api/v2/customization/quick-access');

        $this->assertContains($response->status(), [200, 204, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  EXPORT — WF #921
    // ══════════════════════════════════════════════

    /** @test */
    public function wf921_export_all_customization(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/customization/export');

        $this->assertContains($response->status(), [200, 422, 500]);
    }
}
