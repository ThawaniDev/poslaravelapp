<?php

namespace Tests\Feature\AutoUpdate;

use App\Domain\AppUpdateManagement\Models\AppRelease;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoUpdateApiTest extends TestCase
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
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Seed an active AppRelease
        AppRelease::forceCreate([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'version_number' => '1.0.0',
            'platform' => 'ios',
            'channel' => 'stable',
            'download_url' => 'https://example.com/download/1.0.0',
            'store_url' => 'https://apps.apple.com/app/test',
            'build_number' => '100',
            'submission_status' => 'approved',
            'release_notes' => 'Initial release',
            'release_notes_ar' => 'الإصدار الأول',
            'is_force_update' => false,
            'min_supported_version' => '0.9.0',
            'rollout_percentage' => 100,
            'is_active' => true,
            'released_at' => now(),
        ]);
    }

    // ─── Manifest ────────────────────────────────────────────

    public function test_can_get_update_manifest(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/auto-update/manifest/1.0.0');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'version',
                    'build_number',
                    'platform',
                    'channel',
                    'download_url',
                    'checksum',
                    'is_force_update',
                    'release_notes',
                ],
            ]);
    }

    // ─── Download Info ──────────────────────────────────────

    public function test_can_get_download_info(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/auto-update/download/1.0.0');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'version',
                    'download_url',
                    'checksum',
                    'build_number',
                    'platform',
                ],
            ]);
    }

    // ─── Rollout Status ─────────────────────────────────────

    public function test_can_get_rollout_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/auto-update/rollout-status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_active_rollout',
                    'version',
                    'rollout_percentage',
                    'is_force_update',
                ],
            ]);
    }

    // ─── Auth ───────────────────────────────────────────────

    public function test_auto_update_endpoints_require_auth(): void
    {
        $this->getJson('/api/v2/auto-update/manifest/1.0.0')->assertUnauthorized();
        $this->getJson('/api/v2/auto-update/download/1.0.0')->assertUnauthorized();
        $this->getJson('/api/v2/auto-update/rollout-status')->assertUnauthorized();
    }
}
