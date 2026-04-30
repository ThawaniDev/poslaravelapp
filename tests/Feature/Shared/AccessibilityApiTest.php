<?php

namespace Tests\Feature\Shared;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Schema::hasTable('organizations')) {
            \Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('slug')->unique();
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('stores')) {
            \Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organization_id')->constrained('organizations');
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->unique();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('users')) {
            \Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password_hash');
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('personal_access_tokens')) {
            \Schema::create('personal_access_tokens', function ($t) {
                $t->id();
                $t->uuidMorphs('tokenable');
                $t->string('name');
                $t->string('token', 64)->unique();
                $t->text('abilities')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
            });
        }

        // Drop and recreate user_preferences with accessibility_json column (SQLite only)
        if (\DB::connection()->getDriverName() === 'sqlite') {
            \Schema::dropIfExists('user_preferences');
            \Schema::create('user_preferences', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('user_id')->constrained('users');
                $t->string('pos_handedness')->nullable();
                $t->string('font_size')->nullable();
                $t->string('theme')->nullable();
                $t->uuid('pos_layout_id')->nullable();
                $t->json('accessibility_json')->nullable();
            });
        }

        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'accessibility@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->userId = $user->id;
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v2/{$uri}", ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authPut(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authDelete(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJson("/api/v2/{$uri}", [], ['Authorization' => "Bearer {$this->token}"]);
    }

    // ═══════════════ Get Preferences ═══════════════

    public function test_get_defaults(): void
    {
        $res = $this->authGet('accessibility/preferences');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.0, $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
        $this->assertEquals('none', $data['color_blind_mode']);
        $this->assertFalse($data['reduced_motion']);
        $this->assertTrue($data['audio_feedback']);
        $this->assertEquals(0.7, $data['audio_volume']);
        $this->assertTrue($data['visible_focus']);
    }

    public function test_get_custom_prefs(): void
    {
        // Set some preferences first
        $this->authPut('accessibility/preferences', [
            'font_scale' => 1.3,
            'high_contrast' => true,
        ]);

        $res = $this->authGet('accessibility/preferences');
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.3, $data['font_scale']);
        $this->assertTrue($data['high_contrast']);
    }

    // ═══════════════ Update Preferences ═══════════════

    public function test_update_preferences(): void
    {
        $res = $this->authPut('accessibility/preferences', [
            'font_scale' => 1.2,
            'high_contrast' => true,
            'reduced_motion' => true,
            'audio_volume' => 0.5,
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.2, $data['font_scale']);
        $this->assertTrue($data['high_contrast']);
        $this->assertTrue($data['reduced_motion']);
        $this->assertEquals(0.5, $data['audio_volume']);
    }

    public function test_update_color_blind_mode(): void
    {
        $res = $this->authPut('accessibility/preferences', [
            'color_blind_mode' => 'protanopia',
        ]);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('protanopia', $data['color_blind_mode']);
    }

    public function test_update_validation_font_scale_too_low(): void
    {
        $res = $this->authPut('accessibility/preferences', [
            'font_scale' => 0.5,
        ]);
        $res->assertStatus(422);
    }

    public function test_update_validation_font_scale_too_high(): void
    {
        $res = $this->authPut('accessibility/preferences', [
            'font_scale' => 2.0,
        ]);
        $res->assertStatus(422);
    }

    public function test_update_validation_invalid_color_blind(): void
    {
        $res = $this->authPut('accessibility/preferences', [
            'color_blind_mode' => 'invalid',
        ]);
        $res->assertStatus(422);
    }

    // ═══════════════ Reset Preferences ═══════════════

    public function test_reset_preferences(): void
    {
        $this->authPut('accessibility/preferences', [
            'font_scale' => 1.5,
            'high_contrast' => true,
        ]);

        $res = $this->authDelete('accessibility/preferences');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.0, $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
    }

    // ═══════════════ Shortcuts ═══════════════

    public function test_get_default_shortcuts(): void
    {
        $res = $this->authGet('accessibility/shortcuts');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('F2', $data['new_sale']);
        $this->assertEquals('F5', $data['pay']);
        $this->assertEquals('F8', $data['void_item']);
        $this->assertEquals('F1', $data['help']);
    }

    public function test_update_shortcuts(): void
    {
        $res = $this->authPut('accessibility/shortcuts', [
            'shortcuts' => [
                'new_sale' => 'F3',
                'pay' => 'F6',
            ],
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertArrayHasKey('custom_shortcuts', $data);
        $this->assertEquals('F3', $data['custom_shortcuts']['new_sale']);
    }

    // ═══════════════ Auth ═══════════════

    public function test_unauthenticated(): void
    {
        $this->getJson('/api/v2/accessibility/preferences')->assertUnauthorized();
    }

    // ═══════════════ Integration ═══════════════

    public function test_full_workflow(): void
    {
        // 1. Get defaults
        $res = $this->authGet('accessibility/preferences');
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.0, $data['font_scale']);

        // 2. Update
        $this->authPut('accessibility/preferences', [
            'font_scale' => 1.3,
            'high_contrast' => true,
            'audio_feedback' => false,
        ]);

        // 3. Verify persistence
        $res = $this->authGet('accessibility/preferences');
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.3, $data['font_scale']);
        $this->assertTrue($data['high_contrast']);
        $this->assertFalse($data['audio_feedback']);

        // 4. Reset
        $this->authDelete('accessibility/preferences');
        $res = $this->authGet('accessibility/preferences');
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(1.0, $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
        $this->assertTrue($data['audio_feedback']);
    }
}
