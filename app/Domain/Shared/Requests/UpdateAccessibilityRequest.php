<?php

namespace App\Domain\Shared\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'font_scale' => ['sometimes', 'numeric', 'min:0.8', 'max:1.5'],
            'high_contrast' => ['sometimes', 'boolean'],
            'color_blind_mode' => ['sometimes', 'string', 'in:none,protanopia,deuteranopia,tritanopia'],
            'reduced_motion' => ['sometimes', 'boolean'],
            'audio_feedback' => ['sometimes', 'boolean'],
            'audio_volume' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'large_touch_targets' => ['sometimes', 'boolean'],
            'visible_focus' => ['sometimes', 'boolean'],
            'screen_reader_hints' => ['sometimes', 'boolean'],
            'custom_shortcuts' => ['sometimes', 'array'],
            'custom_shortcuts.*' => ['string', 'max:30'],
        ];
    }
}
