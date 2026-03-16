<?php

namespace App\Domain\PosCustomization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo_url' => 'sometimes|nullable|string|max:500',
            'header_line_1' => 'sometimes|nullable|string|max:200',
            'header_line_2' => 'sometimes|nullable|string|max:200',
            'footer_text' => 'sometimes|nullable|string|max:500',
            'show_vat_number' => 'sometimes|boolean',
            'show_loyalty_points' => 'sometimes|boolean',
            'show_barcode' => 'sometimes|boolean',
            'paper_width_mm' => 'sometimes|integer|in:58,80',
        ];
    }
}
