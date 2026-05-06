<?php

namespace Tests\Feature\Shared;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive API feature tests for the Accessibility endpoints.
 * Test groups:
 *  1. Authentication (unauthenticated → 401 on all endpoints)
 *  2. Get preferences (defaults, custom, structure, required keys)
 *  3. Update preferences (full, partial merge, all color-blind modes, booleans)
 *  4. Validation (font_scale bounds, color_blind_mode, audio_volume, type checks)
 *  5. Reset preferences
 *  6. Shortcuts (get defaults, update, max-length, structure)
 *  7. Multi-user isolation
 *  8. Full workflow
 */
class AccessibilityApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $userId;
    private string $tokenB;
    private string $userIdB;

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

        $org   = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $store = Store::create(['organization_id' => $org->id, 'name' => 'Test Store', 'slug' => 'test-store']);

        $userA = User::create(['name' => 'User A', 'email' => 'a@accessibility.test',
            'store_id' => $store->id, 'password_hash' => bcrypt('password')]);
        $this->userId = $userA->id;
        $this->token  = $userA->createToken('test-a', ['*'])->plainTextToken;

        $userB = User::create(['name' => 'User B', 'email' => 'b@accessibility.test',
            'store_id' => $store->id, 'password_hash' => bcrypt('password')]);
        $this->userIdB = $userB->id;
        $this->tokenB  = $userB->createToken('test-b', ['*'])->plainTextToken;
    }

    private function authGet(string $uri, string $token = ''): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v2/{$uri}", ['Authorization' => 'Bearer ' . ($token ?: $this->token)]);
    }

    private function authPut(string $uri, array $data = [], string $token = ''): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/v2/{$uri}", $data, ['Authorization' => 'Bearer ' . ($token ?: $this->token)]);
    }

    private function authDelete(string $uri, string $token = ''): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJson("/api/v2/{$uri}", [], ['Authorization' => 'Bearer ' . ($token ?: $this->token)]);
    }

    private function prefsData(\Illuminate\Testing\TestResponse $res): array
    {
        return json_decode($res->getContent(), true)['data'];
    }

    // ═══════════════ 1. Authentication ═══════════════

    public function test_all_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v2/accessibility/preferences')->assertUnauthorized();
        $this->putJson('/api/v2/accessibility/preferences', [])->assertUnauthorized();
        $this->deleteJson('/api/v2/accessibility/preferences')->assertUnauthorized();
        $this->getJson('/api/v2/accessibility/shortcuts')->assertUnauthorized();
        $this->putJson('/api/v2/accessibility/shortcuts', [])->assertUnauthorized();
    }

    // ═══════════════ 2. Get Preferences ═══════════════

    public function test_get_defaults_when_no_record(): void
    {
        $data = $this->prefsData($this->authGet('accessibility/preferences')->assertOk());
        $this->assertEquals(1.0,    $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
        $this->assertEquals('none', $data['color_blind_mode']);
        $this->assertFalse($data['reduced_motion']);
        $this->assertTrue($data['audio_feedback']);
        $this->assertEquals(0.7,    $data['audio_volume']);
        $this->assertFalse($data['large_touch_targets']);
        $this->assertTrue($data['visible_focus']);
        $this->assertTrue($data['screen_reader_hints']);
        $this->assertArrayHasKey('custom_shortcuts', $data);
    }

    public function test_response_has_data_and_message_keys(): void
    {
        $body = json_decode($this->authGet('accessibility/preferences')->getContent(), true);
        $this->assertArrayHasKey('data',    $body);
        $this->assertArrayHasKey('message', $body);
    }

    public function test_get_includes_all_required_keys(): void
    {
        $data = $this->prefsData($this->authGet('accessibility/preferences'));
        foreach (['font_scale','high_contrast','color_blind_mode','reduced_motion',
                  'audio_feedback','audio_volume','large_touch_targets',
                  'visible_focus','screen_reader_hints','custom_shortcuts'] as $key) {
            $this->assertArrayHasKey($key, $data, "Missing required key: {$key}");
        }
    }

    public function test_get_returns_saved_values_after_update(): void
    {
        $this->authPut('accessibility/preferences', ['font_scale' => 1.3, 'high_contrast' => true]);
        $data = $this->prefsData($this->authGet('accessibility/preferences'));
        $this->assertEquals(1.3, $data['font_scale']);
        $this->assertTrue($data['high_contrast']);
    }

    // ═══════════════ 3. Update Preferences ═══════════════

    public function test_update_all_preferences(): void
    {
        $data = $this->prefsData($this->authPut('accessibility/preferences', [
            'font_scale' => 1.2, 'high_contrast' => true, 'color_blind_mode' => 'protanopia',
            'reduced_motion' => true, 'audio_feedback' => false, 'audio_volume' => 0.5,
            'large_touch_targets' => true, 'visible_focus' => false, 'screen_reader_hints' => false,
        ])->assertOk());
        $this->assertEquals(1.2,          $data['font_scale']);
        $this->assertTrue($data['high_contrast']);
        $this->assertEquals('protanopia', $data['color_blind_mode']);
        $this->assertTrue($data['reduced_motion']);
        $this->assertFalse($data['audio_feedback']);
        $this->assertEquals(0.5,          $data['audio_volume']);
        $this->assertTrue($data['large_touch_targets']);
        $this->assertFalse($data['visible_focus']);
        $this->assertFalse($data['screen_reader_hints']);
    }

    public function test_partial_update_preserves_previously_saved_fields(): void
    {
        $this->authPut('accessibility/preferences', [
            'font_scale' => 1.4, 'high_contrast' => true, 'audio_volume' => 0.3,
        ]);
        $data = $this->prefsData($this->authPut('accessibility/preferences', ['color_blind_mode' => 'deuteranopia']));
        $this->assertEquals(1.4,            $data['font_scale'],       'font_scale must be preserved');
        $this->assertTrue($data['high_contrast'],                      'high_contrast must be preserved');
        $this->assertEquals(0.3,            $data['audio_volume'],     'audio_volume must be preserved');
        $this->assertEquals('deuteranopia', $data['color_blind_mode'], 'color_blind_mode must be updated');
    }

    public function test_update_font_scale_minimum_boundary(): void
    {
        $this->assertEquals(0.8, $this->prefsData($this->authPut('accessibility/preferences', ['font_scale' => 0.8]))['font_scale']);
    }

    public function test_update_font_scale_maximum_boundary(): void
    {
        $this->assertEquals(1.5, $this->prefsData($this->authPut('accessibility/preferences', ['font_scale' => 1.5]))['font_scale']);
    }

    public function test_update_audio_volume_zero(): void
    {
        $this->assertEquals(0.0, $this->prefsData($this->authPut('accessibility/preferences', ['audio_volume' => 0.0]))['audio_volume']);
    }

    public function test_update_audio_volume_maximum(): void
    {
        $this->assertEquals(1.0, $this->prefsData($this->authPut('accessibility/preferences', ['audio_volume' => 1.0]))['audio_volume']);
    }

    public function test_update_color_blind_mode_protanopia(): void
    {
        $this->assertEquals('protanopia',  $this->prefsData($this->authPut('accessibility/preferences', ['color_blind_mode' => 'protanopia']))['color_blind_mode']);
    }

    public function test_update_color_blind_mode_deuteranopia(): void
    {
        $this->assertEquals('deuteranopia', $this->prefsData($this->authPut('accessibility/preferences', ['color_blind_mode' => 'deuteranopia']))['color_blind_mode']);
    }

    public function test_update_color_blind_mode_tritanopia(): void
    {
        $this->assertEquals('tritanopia',  $this->prefsData($this->authPut('accessibility/preferences', ['color_blind_mode' => 'tritanopia']))['color_blind_mode']);
    }

    public function test_update_color_blind_mode_back_to_none(): void
    {
        $this->authPut('accessibility/preferences', ['color_blind_mode' => 'protanopia']);
        $this->assertEquals('none', $this->prefsData($this->authPut('accessibility/preferences', ['color_blind_mode' => 'none']))['color_blind_mode']);
    }

    public function test_update_large_touch_targets(): void
    {
        $this->assertTrue($this->prefsData($this->authPut('accessibility/preferences', ['large_touch_targets' => true]))['large_touch_targets']);
    }

    public function test_update_screen_reader_hints_false(): void
    {
        $this->assertFalse($this->prefsData($this->authPut('accessibility/preferences', ['screen_reader_hints' => false]))['screen_reader_hints']);
    }

    public function test_update_visible_focus_false(): void
    {
        $this->assertFalse($this->prefsData($this->authPut('accessibility/preferences', ['visible_focus' => false]))['visible_focus']);
    }

    public function test_update_reduced_motion(): void
    {
        $this->assertTrue($this->prefsData($this->authPut('accessibility/preferences', ['reduced_motion' => true]))['reduced_motion']);
    }

    // ═══════════════ 4. Validation ═══════════════

    public function test_validation_font_scale_too_low():         void { $this->authPut('accessibility/preferences', ['font_scale' => 0.5])->assertStatus(422); }
    public function test_validation_font_scale_too_high():        void { $this->authPut('accessibility/preferences', ['font_scale' => 2.0])->assertStatus(422); }
    public function test_validation_font_scale_non_numeric():     void { $this->authPut('accessibility/preferences', ['font_scale' => 'large'])->assertStatus(422); }
    public function test_validation_invalid_color_blind_mode():   void { $this->authPut('accessibility/preferences', ['color_blind_mode' => 'bad'])->assertStatus(422); }
    public function test_validation_audio_volume_below_zero():    void { $this->authPut('accessibility/preferences', ['audio_volume' => -0.1])->assertStatus(422); }
    public function test_validation_audio_volume_above_one():     void { $this->authPut('accessibility/preferences', ['audio_volume' => 1.1])->assertStatus(422); }
    public function test_validation_high_contrast_non_boolean():  void { $this->authPut('accessibility/preferences', ['high_contrast' => 'yes'])->assertStatus(422); }
    public function test_validation_reduced_motion_non_boolean(): void { $this->authPut('accessibility/preferences', ['reduced_motion' => 'yes'])->assertStatus(422); }
    public function test_validation_audio_feedback_non_boolean(): void { $this->authPut('accessibility/preferences', ['audio_feedback' => 'enabled'])->assertStatus(422); }

    // ═══════════════ 5. Reset Preferences ═══════════════

    public function test_reset_returns_all_defaults(): void
    {
        $this->authPut('accessibility/preferences', ['font_scale' => 1.5, 'high_contrast' => true, 'audio_volume' => 0.2]);
        $data = $this->prefsData($this->authDelete('accessibility/preferences')->assertOk());
        $this->assertEquals(1.0,    $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
        $this->assertEquals('none', $data['color_blind_mode']);
        $this->assertTrue($data['audio_feedback']);
        $this->assertEquals(0.7,    $data['audio_volume']);
    }

    public function test_reset_on_fresh_user_returns_defaults(): void
    {
        $data = $this->prefsData($this->authDelete('accessibility/preferences'));
        $this->assertEquals(1.0,  $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
    }

    public function test_can_update_after_reset(): void
    {
        $this->authPut('accessibility/preferences', ['font_scale' => 1.5]);
        $this->authDelete('accessibility/preferences');
        $this->assertEquals(1.2, $this->prefsData($this->authPut('accessibility/preferences', ['font_scale' => 1.2]))['font_scale']);
    }

    // ═══════════════ 6. Shortcuts ═══════════════

    public function test_get_default_shortcuts(): void
    {
        $data = json_decode($this->authGet('accessibility/shortcuts')->assertOk()->getContent(), true)['data'];
        $this->assertEquals('F2',     $data['new_sale']);
        $this->assertEquals('F5',     $data['pay']);
        $this->assertEquals('F8',     $data['void_item']);
        $this->assertEquals('F10',    $data['open_drawer']);
        $this->assertEquals('Ctrl+L', $data['lock_screen']);
        $this->assertEquals('F1',     $data['help']);
    }

    public function test_update_shortcuts_persists(): void
    {
        $data = json_decode(
            $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3', 'pay' => 'F6']])->assertOk()->getContent(),
            true
        )['data'];
        $this->assertArrayHasKey('custom_shortcuts', $data);
        $this->assertEquals('F3', $data['custom_shortcuts']['new_sale']);
        $this->assertEquals('F6', $data['custom_shortcuts']['pay']);
    }

    public function test_shortcuts_reflected_in_preferences(): void
    {
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3']]);
        $data = $this->prefsData($this->authGet('accessibility/preferences'));
        $this->assertEquals('F3', $data['custom_shortcuts']['new_sale']);
    }

    public function test_shortcuts_requires_shortcuts_key(): void
    {
        $this->authPut('accessibility/shortcuts', ['new_sale' => 'F3'])->assertStatus(422);
    }

    public function test_shortcut_value_over_30_chars_rejected(): void
    {
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => str_repeat('A', 31)]])->assertStatus(422);
    }

    public function test_shortcut_value_exactly_30_chars_accepted(): void
    {
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => str_repeat('A', 30)]])->assertOk();
    }

    public function test_shortcut_non_string_value_rejected(): void
    {
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => 123]])->assertStatus(422);
    }

    // ═══════════════ 7. Multi-user Isolation ═══════════════

    public function test_users_have_isolated_preferences(): void
    {
        $this->authPut('accessibility/preferences', ['font_scale' => 1.5], $this->token);
        $this->authPut('accessibility/preferences', ['font_scale' => 0.9], $this->tokenB);
        $this->assertEquals(1.5, $this->prefsData($this->authGet('accessibility/preferences', $this->token))['font_scale']);
        $this->assertEquals(0.9, $this->prefsData($this->authGet('accessibility/preferences', $this->tokenB))['font_scale']);
    }

    public function test_resetting_user_a_does_not_affect_user_b(): void
    {
        $this->authPut('accessibility/preferences', ['font_scale' => 1.3], $this->tokenB);
        $this->authDelete('accessibility/preferences', $this->token);
        $this->assertEquals(1.3, $this->prefsData($this->authGet('accessibility/preferences', $this->tokenB))['font_scale']);
    }

    public function test_shortcuts_are_isolated_between_users(): void
    {
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3']], $this->token);
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F4']], $this->tokenB);
        $sA = json_decode($this->authGet('accessibility/shortcuts', $this->token)->getContent(),  true)['data'];
        $sB = json_decode($this->authGet('accessibility/shortcuts', $this->tokenB)->getContent(), true)['data'];
        $this->assertEquals('F3', $sA['new_sale']);
        $this->assertEquals('F4', $sB['new_sale']);
    }

    // ═══════════════ 8. Full Workflow ═══════════════

    public function test_full_accessibility_workflow(): void
    {
        // 1. Defaults
        $this->assertEquals(1.0, $this->prefsData($this->authGet('accessibility/preferences'))['font_scale']);

        // 2. Update
        $this->authPut('accessibility/preferences', [
            'font_scale' => 1.3, 'high_contrast' => true, 'audio_feedback' => false,
        ]);

        // 3. Partial update — merge verification
        $this->authPut('accessibility/preferences', ['color_blind_mode' => 'tritanopia']);

        // 4. Verify merge integrity
        $data = $this->prefsData($this->authGet('accessibility/preferences'));
        $this->assertEquals(1.3,          $data['font_scale']);
        $this->assertTrue($data['high_contrast']);
        $this->assertFalse($data['audio_feedback']);
        $this->assertEquals('tritanopia', $data['color_blind_mode']);

        // 5. Shortcuts
        $this->authPut('accessibility/shortcuts', ['shortcuts' => ['new_sale' => 'F3']]);
        $this->assertEquals('F3', json_decode($this->authGet('accessibility/shortcuts')->getContent(), true)['data']['new_sale']);
        $this->assertEquals('F3', $this->prefsData($this->authGet('accessibility/preferences'))['custom_shortcuts']['new_sale']);

        // 6. Reset
        $this->authDelete('accessibility/preferences');
        $data = $this->prefsData($this->authGet('accessibility/preferences'));
        $this->assertEquals(1.0,    $data['font_scale']);
        $this->assertFalse($data['high_contrast']);
        $this->assertTrue($data['audio_feedback']);
        $this->assertEquals('none', $data['color_blind_mode']);
    }
}
