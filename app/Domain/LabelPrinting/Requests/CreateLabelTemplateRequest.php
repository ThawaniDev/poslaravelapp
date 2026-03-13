<?php

namespace App\Domain\LabelPrinting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLabelTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'label_width_mm'  => ['required', 'numeric', 'min:20', 'max:200'],
            'label_height_mm' => ['required', 'numeric', 'min:15', 'max:150'],
            'layout_json'     => ['required', 'array'],
            'is_default'      => ['sometimes', 'boolean'],
        ];
    }
}
