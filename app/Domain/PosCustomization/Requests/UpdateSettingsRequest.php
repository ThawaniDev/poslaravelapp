<?php

namespace App\Domain\PosCustomization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => 'sometimes|in:light,dark,custom',
            'primary_color' => 'sometimes|string|max:9',
            'secondary_color' => 'sometimes|string|max:9',
            'accent_color' => 'sometimes|string|max:9',
            'font_scale' => 'sometimes|numeric|min:0.5|max:2.0',
            'handedness' => 'sometimes|in:left,right,center',
            'grid_columns' => 'sometimes|integer|min:1|max:8',
            'show_product_images' => 'sometimes|boolean',
            'show_price_on_grid' => 'sometimes|boolean',
            'cart_display_mode' => 'sometimes|in:compact,detailed',
            'layout_direction' => 'sometimes|in:ltr,rtl,auto',
        ];
    }
}
