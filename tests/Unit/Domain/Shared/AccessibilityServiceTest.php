<?php

namespace Tests\Unit\Domain\Shared;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Shared\Models\UserPreference;
use App\Domain\Shared\Services\AccessibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for AccessibilityService.
 *
 * Covers:
 *  - getPreferences: defaults when no record, custom values from DB
 *  - updatePreferences: creates new record, merges partial updates (critical)
 *  - resetPreferences: nullifies record, returns defaults
 *  - getShortcuts: returns saved custom shortcuts
 *  - updateShortcuts: persists shortcuts inside accessibility_json
 *  - Multi-user isolation: user A's prefs don't bleed into user B
 */
class AccessibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccessibilityService $service;
    private User $userA;
    private User $userB;

    // Defaults from AccessibilityService::DEFAULTS (mirrored here for assertions)
    private const DEFAULTS = [
        'font_scale'        => 1.0,
        'high_contrast'     => false,
        'color_blind_mode'  => 'none',
        'reduced_motion'    => false,
        'audio_feedback'    => true,
        'audio_volume'      => 0.7,
        'large_touch_targets' => false,
        'visible_focus'     => true,
        'screen_reader_hints' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccessibilityService::class);

        $org = Organization::create([
            'name'          => 'Accessibility Test Org',
            'business_type' => 'grocery',
            'country'       => 'SA',
        ]);

        $store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Accessibility Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->userA = User::create([
            'name'          => 'User A',
            'email'         => 'user.a@accessibility.test',
            'password_hash' => bcrypt('password'),
            'store_id'      => $store->id,
            'organization_id' => $org->id,
            'role'          => 'cashier',
            'is_active'     => true,
        ]);

        $this->userB = User::create([
            'name'          => 'User B',
            'email'         => 'user.b@accessibility.test',
            'password_hash' => bcrypt('password'),
            'store_id'      => $store->id,
            'organization_id' => $org->id,
            'role'          => 'cashier',
            'is_active'     => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // getPreferences
    // ═══════════════════════════════════════════════════════════

    public function test_get_preferences_returns_all_defaults_when_no_record_exists(): void
    {
        $prefs = $this->service->getPreferences($this->userA->id);

        $this->assertEquals(1.0, $prefs['font_scale']);
        $this->assertFalse($prefs['high_contrast']);
        $this->assertEquals('none', $prefs['color_blind_mode']);
        $this->assertFalse($prefs['reduced_motion']);
        $this->assertTrue($prefs['audio_feedback']);
        $this->assertEquals(0.7, $prefs['audio_volume']);
        $this->assertFalse($prefs['large_touch_targets']);
        $this->assertTrue($prefs['visible_focus']);
        $this->assertTrue($prefs['screen_reader_hints']);
        $this->assertIsArray($prefs['custom_shortcuts']);
        $this->assertEquals('F2', $prefs['custom_shortcuts']['new_sale']);
        $this->assertEquals('F5', $prefs['custom_shortcuts']['pay']);
    }

    public function test_get_preferences_returns_saved_values_merged_with_defaults(): void
    {
        UserPreference::create([
            'user_id'          => $this->userA->id,
            'accessibility_json' => [
                'font_scale'   => 1.4,
                'high_contrast' => true,
                // Remaining fields should come from defaults
            ],
        ]);

        $prefs = $this->service->getPreferences($this->userA->id);

        $this->assertEquals(1.4, $prefs['font_scale']);
        $this->assertTrue($prefs['high_contrast']);
        // Default fields still present
        $this->assertEquals('none', $prefs['color_blind_mode']);
        $this->assertTrue($prefs['audio_feedback']);
        $this->assertEquals(0.7, $prefs['audio_volume']);
    }

    public function test_get_preferences_returns_null_accessibility_json_as_defaults(): void
    {
        UserPreference::create([
            'user_id'          => $this->userA->id,
            'accessibility_json' => null,
        ]);

        $prefs = $this->service->getPreferences($this->userA->id);

        foreach (self::DEFAULTS as $key => $defaultValue) {
            $this->assertEquals($defaultValue, $prefs[$key], "Default for '{$key}' should be {$defaultValue}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // updatePreferences — CRITICAL: must MERGE, not replace
    // ═══════════════════════════════════════════════════════════

    public function test_update_preferences_creates_record_when_none_exists(): void
    {
        $this->assertDatabaseMissing('user_preferences', ['user_id' => $this->userA->id]);

        $this->service->updatePreferences($this->userA->id, ['font_scale' => 1.2]);

        $this->assertDatabaseHas('user_preferences', ['user_id' => $this->userA->id]);
    }

    public function test_update_preferences_returns_merged_data(): void
    {
        $result = $this->service->updatePreferences($this->userA->id, [
            'font_scale'   => 1.3,
            'high_contrast' => true,
        ]);

        $this->assertEquals(1.3, $result['font_scale']);
        $this->assertTrue($result['high_contrast']);
        // Defaults still present for untouched fields
        $this->assertEquals('none', $result['color_blind_mode']);
        $this->assertTrue($result['audio_feedback']);
    }

    public function test_update_preferences_partial_update_preserves_existing_fields(): void
    {
        // First update: set font_scale and high_contrast
        $this->service->updatePreferences($this->userA->id, [
            'font_scale'   => 1.5,
            'high_contrast' => true,
            'audio_volume' => 0.3,
        ]);

        // Second update: only change color_blind_mode
        $result = $this->service->updatePreferences($this->userA->id, [
            'color_blind_mode' => 'protanopia',
        ]);

        // Previously saved values must still be there
        $this->assertEquals(1.5, $result['font_scale']);
        $this->assertTrue($result['high_contrast']);
        $this->assertEquals(0.3, $result['audio_volume']);
        // New change applied
        $this->assertEquals('protanopia', $result['color_blind_mode']);
    }

    public function test_update_preferences_overwrites_specific_field_on_second_call(): void
    {
        $this->service->updatePreferences($this->userA->id, ['font_scale' => 1.2]);
        $result = $this->service->updatePreferences($this->userA->id, ['font_scale' => 1.4]);

        $this->assertEquals(1.4, $result['font_scale']);
    }

    public function test_update_preferences_persists_all_preference_fields(): void
    {
        $result = $this->service->updatePreferences($this->userA->id, [
            'font_scale'         => 1.2,
            'high_contrast'      => true,
            'color_blind_mode'   => 'deuteranopia',
            'reduced_motion'     => true,
            'audio_feedback'     => false,
            'audio_volume'       => 0.4,
            'large_touch_targets' => true,
            'visible_focus'      => false,
            'screen_reader_hints' => false,
        ]);

        $this->assertEquals(1.2, $result['font_scale']);
        $this->assertTrue($result['high_contrast']);
        $this->assertEquals('deuteranopia', $result['color_blind_mode']);
        $this->assertTrue($result['reduced_motion']);
        $this->assertFalse($result['audio_feedback']);
        $this->assertEquals(0.4, $result['audio_volume']);
        $this->assertTrue($result['large_touch_targets']);
        $this->assertFalse($result['visible_focus']);
        $this->assertFalse($result['screen_reader_hints']);
    }

    // ═══════════════════════════════════════════════════════════
    // resetPreferences
    // ═══════════════════════════════════════════════════════════

    public function test_reset_preferences_returns_defaults_when_record_exists(): void
    {
        $this->service->updatePreferences($this->userA->id, [
            'font_scale'   => 1.5,
            'high_contrast' => true,
        ]);

        $result = $this->service->resetPreferences($this->userA->id);

        foreach (self::DEFAULTS as $key => $defaultValue) {
            $this->assertEquals($defaultValue, $result[$key], "After reset, '{$key}' should be default {$defaultValue}");
        }
    }

    public function test_reset_preferences_nullifies_accessibility_json(): void
    {
        $this->service->updatePreferences($this->userA->id, ['font_scale' => 1.5]);
        $this->service->resetPreferences($this->userA->id);

        $pref = UserPreference::where('user_id', $this->userA->id)->first();
        $this->assertNull($pref?->accessibility_json);
    }

    public function test_reset_preferences_returns_defaults_when_no_record_exists(): void
    {
        $result = $this->service->resetPreferences($this->userA->id);

        $this->assertEquals(1.0, $result['font_scale']);
        $this->assertFalse($result['high_contrast']);
    }

    // ═══════════════════════════════════════════════════════════
    // getShortcuts
    // ═══════════════════════════════════════════════════════════

    public function test_get_shortcuts_returns_default_shortcuts_when_none_saved(): void
    {
        $shortcuts = $this->service->getShortcuts($this->userA->id);

        $this->assertIsArray($shortcuts);
        $this->assertEquals('F2', $shortcuts['new_sale']);
        $this->assertEquals('F5', $shortcuts['pay']);
        $this->assertEquals('F8', $shortcuts['void_item']);
        $this->assertEquals('F10', $shortcuts['open_drawer']);
        $this->assertEquals('Ctrl+L', $shortcuts['lock_screen']);
        $this->assertEquals('F1', $shortcuts['help']);
    }

    public function test_get_shortcuts_returns_custom_shortcuts_when_saved(): void
    {
        $this->service->updateShortcuts($this->userA->id, ['new_sale' => 'F3', 'pay' => 'F6']);

        $shortcuts = $this->service->getShortcuts($this->userA->id);

        $this->assertEquals('F3', $shortcuts['new_sale']);
        $this->assertEquals('F6', $shortcuts['pay']);
    }

    // ═══════════════════════════════════════════════════════════
    // updateShortcuts
    // ═══════════════════════════════════════════════════════════

    public function test_update_shortcuts_persists_new_shortcuts(): void
    {
        $result = $this->service->updateShortcuts($this->userA->id, [
            'new_sale' => 'F4',
            'help'     => 'F12',
        ]);

        $this->assertArrayHasKey('custom_shortcuts', $result);
        $this->assertEquals('F4', $result['custom_shortcuts']['new_sale']);
        $this->assertEquals('F12', $result['custom_shortcuts']['help']);
    }

    public function test_update_shortcuts_preserves_other_preferences(): void
    {
        // Set font_scale first
        $this->service->updatePreferences($this->userA->id, [
            'font_scale'   => 1.4,
            'high_contrast' => true,
        ]);

        // Now update shortcuts — font_scale should be preserved
        $result = $this->service->updateShortcuts($this->userA->id, ['new_sale' => 'F3']);

        $this->assertEquals(1.4, $result['font_scale']);
        $this->assertTrue($result['high_contrast']);
        $this->assertEquals('F3', $result['custom_shortcuts']['new_sale']);
    }

    // ═══════════════════════════════════════════════════════════
    // Multi-user Isolation
    // ═══════════════════════════════════════════════════════════

    public function test_user_preferences_are_isolated_between_users(): void
    {
        $this->service->updatePreferences($this->userA->id, ['font_scale' => 1.5, 'high_contrast' => true]);
        $this->service->updatePreferences($this->userB->id, ['font_scale' => 0.9, 'high_contrast' => false]);

        $prefsA = $this->service->getPreferences($this->userA->id);
        $prefsB = $this->service->getPreferences($this->userB->id);

        $this->assertEquals(1.5, $prefsA['font_scale']);
        $this->assertTrue($prefsA['high_contrast']);

        $this->assertEquals(0.9, $prefsB['font_scale']);
        $this->assertFalse($prefsB['high_contrast']);
    }

    public function test_resetting_user_a_does_not_affect_user_b(): void
    {
        $this->service->updatePreferences($this->userA->id, ['font_scale' => 1.5]);
        $this->service->updatePreferences($this->userB->id, ['font_scale' => 1.3]);

        $this->service->resetPreferences($this->userA->id);

        $prefsB = $this->service->getPreferences($this->userB->id);
        $this->assertEquals(1.3, $prefsB['font_scale']);
    }

    public function test_shortcut_updates_are_isolated_between_users(): void
    {
        $this->service->updateShortcuts($this->userA->id, ['new_sale' => 'F3']);
        $this->service->updateShortcuts($this->userB->id, ['new_sale' => 'F4']);

        $shortcutsA = $this->service->getShortcuts($this->userA->id);
        $shortcutsB = $this->service->getShortcuts($this->userB->id);

        $this->assertEquals('F3', $shortcutsA['new_sale']);
        $this->assertEquals('F4', $shortcutsB['new_sale']);
    }
}
