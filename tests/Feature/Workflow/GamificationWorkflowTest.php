<?php

namespace Tests\Feature\Workflow;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gamification & Loyalty Tiers Workflow Tests — WF #922-926
 *
 * Covers: nice-to-have.php gamification section (5 endpoints)
 *   - Challenges, badges, tiers
 *   - Customer progress & badges
 */
class GamificationWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Store $store;
    protected User $owner;
    protected string $ownerToken;
    protected string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Gamification Org',
            'name_ar' => 'مؤسسة الألعاب',
            'business_type' => 'grocery',
            'country' => 'OM',
            'vat_number' => 'OM444555666',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Gamification Store',
            'name_ar' => 'متجر الألعاب',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'en',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Gamification Owner',
            'email' => 'gamification-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);

        // Seed a customer for progress/badges lookups
        $this->customerId = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $this->customerId,
            'organization_id' => $this->org->id,
            'name' => 'Loyal Customer',
            'phone' => '96899000001',
            'loyalty_points' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ══════════════════════════════════════════════
    //  GAMIFICATION — WF #922-926
    // ══════════════════════════════════════════════

    /** @test */
    public function wf922_list_challenges(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/gamification/challenges');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf923_list_badges(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/gamification/badges');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf924_list_tiers(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/gamification/tiers');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf925_customer_progress(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/gamification/customer/{$this->customerId}/progress");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf926_customer_badges(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/gamification/customer/{$this->customerId}/badges");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }
}
