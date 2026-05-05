<?php

namespace Tests\Feature\Domain\ContentOnboarding;

use App\Domain\Auth\Models\User;
use App\Domain\ContentOnboarding\Models\OnboardingStep;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive API tests for GET /api/v2/core/onboarding/steps.
 *
 * Route requires auth:sanctum + permission:onboarding.manage
 * (permission check is bypassed by BypassPermissionMiddleware in tests).
 *
 * Two modes tested:
 *   1. DB is empty  → hardcoded STEP_ORDER fallback (8 steps)
 *   2. DB has rows  → returns DB steps, ordered by sort_order, step_number
 */
class OnboardingStepsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name'    => 'Onboarding Test Org',
            'name_ar' => 'مؤسسة اختبار الإعداد',
            'country' => 'SA',
        ]);
        $store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Onboarding Test Store',
            'currency'        => 'SAR',
        ]);
        $this->user = User::create([
            'name'            => 'Test User',
            'email'           => 'onboarding-steps-test@test.example',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $store->id,
            'organization_id' => $org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Authentication
    // ═══════════════════════════════════════════════════════════════════════

    public function test_steps_requires_authentication(): void
    {
        $this->getJson('/api/v2/core/onboarding/steps')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Fallback mode (no DB rows)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_steps_returns_8_hardcoded_steps_when_db_empty(): void
    {
        $this->assertDatabaseCount('onboarding_steps', 0);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $response->assertOk();
        $this->assertCount(8, $response->json('data'));
    }

    public function test_fallback_first_step_is_welcome(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $this->assertEquals('welcome', $response->json('data.0.key'));
    }

    public function test_fallback_last_step_is_review(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $steps = $response->json('data');
        $this->assertEquals('review', end($steps)['key']);
    }

    public function test_fallback_step_order_matches_constant(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $keys = collect($response->json('data'))->pluck('key')->toArray();

        $this->assertEquals(OnboardingService::STEP_ORDER, $keys);
    }

    public function test_fallback_all_steps_are_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        foreach ($response->json('data') as $step) {
            $this->assertTrue($step['is_required'], "Fallback step '{$step['key']}' must be is_required=true");
        }
    }

    public function test_fallback_steps_have_bilingual_labels(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        foreach ($response->json('data') as $step) {
            $this->assertNotEmpty($step['label_en'], "Step '{$step['key']}' must have label_en");
            $this->assertNotEmpty($step['label_ar'], "Step '{$step['key']}' must have label_ar");
        }
    }

    public function test_fallback_response_shape(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'key', 'order', 'step_number',
                        'label_en', 'label_ar',
                        'description', 'description_ar',
                        'is_required',
                    ],
                ],
            ]);
    }

    public function test_fallback_step_number_starts_at_1(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $this->assertEquals(1, $response->json('data.0.step_number'));
    }

    public function test_fallback_step_numbers_are_sequential(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $numbers = collect($response->json('data'))->pluck('step_number')->toArray();

        for ($i = 0; $i < count($numbers); $i++) {
            $this->assertEquals($i + 1, $numbers[$i], "step_number should be sequential starting from 1");
        }
    }

    public function test_fallback_known_step_labels(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $businessTypeStep = collect($response->json('data'))->firstWhere('key', 'business_type');

        $this->assertNotNull($businessTypeStep);
        $this->assertEquals('Business Type', $businessTypeStep['label_en']);
        $this->assertEquals('نوع النشاط', $businessTypeStep['label_ar']);
    }

    public function test_fallback_description_is_null(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        // Fallback steps have null descriptions (only DB steps have descriptions)
        foreach ($response->json('data') as $step) {
            $this->assertNull($step['description']);
            $this->assertNull($step['description_ar']);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  DB mode (OnboardingStep records present)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_db_steps_override_fallback(): void
    {
        $this->createDbStep('Welcome to POS', 'مرحباً بك في نقطة البيع', 1, 0, true);
        $this->createDbStep('Setup Business Info', 'إعداد معلومات النشاط', 2, 1, true);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'), 'Should use DB steps, not the 8 fallback steps');
    }

    public function test_db_steps_ordered_by_sort_order_then_step_number(): void
    {
        $this->createDbStep('Step C', 'خطوة ج', 3, 2);
        $this->createDbStep('Step A', 'خطوة أ', 1, 0);
        $this->createDbStep('Step B', 'خطوة ب', 2, 1);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $labels = collect($response->json('data'))->pluck('label_en')->toArray();
        $this->assertEquals(['Step A', 'Step B', 'Step C'], $labels);
    }

    public function test_db_step_bilingual_labels(): void
    {
        $this->createDbStep('Business Setup', 'إعداد النشاط', 1, 0, true, 'Configure your business.', 'اضبط نشاطك التجاري.');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $step = $response->json('data.0');
        $this->assertEquals('Business Setup', $step['label_en']);
        $this->assertEquals('إعداد النشاط', $step['label_ar']);
        $this->assertEquals('Configure your business.', $step['description']);
        $this->assertEquals('اضبط نشاطك التجاري.', $step['description_ar']);
    }

    public function test_db_step_is_required_boolean(): void
    {
        $this->createDbStep('Required Step', 'خطوة مطلوبة', 1, 0, true);
        $this->createDbStep('Optional Step', 'خطوة اختيارية', 2, 1, false);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $steps = $response->json('data');
        $this->assertTrue($steps[0]['is_required']);
        $this->assertFalse($steps[1]['is_required']);
    }

    public function test_db_step_key_is_slugified_title(): void
    {
        $this->createDbStep('Business Information', 'معلومات النشاط', 1, 0);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $this->assertEquals('business-information', $response->json('data.0.key'));
    }

    public function test_db_step_order_matches_sort_order(): void
    {
        $this->createDbStep('First Step', 'الخطوة الأولى', 1, 5);
        $this->createDbStep('Second Step', 'الخطوة الثانية', 2, 10);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $steps = $response->json('data');
        $this->assertEquals(5, $steps[0]['order']);
        $this->assertEquals(10, $steps[1]['order']);
    }

    public function test_db_step_step_number_is_integer(): void
    {
        $this->createDbStep('Step One', 'الخطوة 1', 1, 0);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $this->assertIsInt($response->json('data.0.step_number'));
    }

    public function test_db_steps_response_shape(): void
    {
        $this->createDbStep('My Step', 'خطوتي', 1, 0, true, 'Desc.', 'وصف.');

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/onboarding/steps');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'key', 'order', 'step_number',
                        'label_en', 'label_ar',
                        'description', 'description_ar',
                        'is_required',
                    ],
                ],
            ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────

    private function createDbStep(
        string $title,
        string $titleAr,
        int $stepNumber,
        int $sortOrder,
        bool $isRequired = true,
        ?string $description = null,
        ?string $descriptionAr = null,
    ): OnboardingStep {
        return OnboardingStep::create([
            'title'          => $title,
            'title_ar'       => $titleAr,
            'step_number'    => $stepNumber,
            'sort_order'     => $sortOrder,
            'is_required'    => $isRequired,
            'description'    => $description,
            'description_ar' => $descriptionAr,
        ]);
    }
}
