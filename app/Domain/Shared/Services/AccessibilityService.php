<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\UserPreference;

class AccessibilityService
{
    private const DEFAULTS = [
        'font_scale' => 1.0,
        'high_contrast' => false,
        'color_blind_mode' => 'none',
        'reduced_motion' => false,
        'audio_feedback' => true,
        'audio_volume' => 0.7,
        'large_touch_targets' => false,
        'visible_focus' => true,
        'screen_reader_hints' => true,
        'custom_shortcuts' => [
            'new_sale' => 'F2',
            'pay' => 'F5',
            'void_item' => 'F8',
            'open_drawer' => 'F10',
            'lock_screen' => 'Ctrl+L',
            'help' => 'F1',
        ],
    ];

    public function getPreferences(string $userId): array
    {
        $pref = UserPreference::where('user_id', $userId)->first();
        $accessibility = $pref?->accessibility_json ?? [];

        return array_merge(self::DEFAULTS, $accessibility);
    }

    public function updatePreferences(string $userId, array $data): array
    {
        // Fetch existing record to MERGE (not replace) partial updates.
        // This ensures that updating only font_scale does not wipe high_contrast, etc.
        $existing = UserPreference::where('user_id', $userId)->value('accessibility_json') ?? [];

        // Merge: new data overrides existing fields; defaults fill any gaps.
        $merged = array_merge($existing, $data);

        UserPreference::updateOrCreate(
            ['user_id' => $userId],
            ['accessibility_json' => $merged],
        );

        return array_merge(self::DEFAULTS, $merged);
    }

    public function resetPreferences(string $userId): array
    {
        $pref = UserPreference::where('user_id', $userId)->first();
        if ($pref) {
            $pref->update(['accessibility_json' => null]);
        }

        return self::DEFAULTS;
    }

    public function getShortcuts(string $userId): array
    {
        $prefs = $this->getPreferences($userId);
        return $prefs['custom_shortcuts'] ?? self::DEFAULTS['custom_shortcuts'];
    }

    public function updateShortcuts(string $userId, array $shortcuts): array
    {
        $current = $this->getPreferences($userId);
        $current['custom_shortcuts'] = $shortcuts;

        return $this->updatePreferences($userId, $current);
    }
}
