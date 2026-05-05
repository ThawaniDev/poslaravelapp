<?php

namespace App\Domain\LabelPrinting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route already guards with permission:labels.manage
    }

    public function rules(): array
    {
        return [
            'name'            => ['sometimes', 'string', 'max:255'],
            'label_width_mm'  => ['sometimes', 'numeric', 'min:20', 'max:200'],
            'label_height_mm' => ['sometimes', 'numeric', 'min:15', 'max:150'],
            'layout_json'     => ['sometimes', 'array'],
            'is_default'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'label_width_mm.min'  => 'Label width must be at least 20 mm.',
            'label_width_mm.max'  => 'Label width cannot exceed 200 mm.',
            'label_height_mm.min' => 'Label height must be at least 15 mm.',
            'label_height_mm.max' => 'Label height cannot exceed 150 mm.',
        ];
    }
}
