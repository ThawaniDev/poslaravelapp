<?php

namespace App\Domain\CashierGamification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreating = $this->isMethod('POST');

        return [
            'slug' => [$isCreating ? 'required' : 'sometimes', 'string', 'max:100'],
            'name_en' => [$isCreating ? 'required' : 'sometimes', 'string', 'max:255'],
            'name_ar' => [$isCreating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'trigger_type' => [$isCreating ? 'required' : 'sometimes', 'string', 'in:sales_champion,speed_star,consistency_king,upsell_master,early_bird,marathon_runner,zero_void,customer_favorite'],
            'trigger_threshold' => ['sometimes', 'numeric', 'min:0'],
            'period' => [$isCreating ? 'required' : 'sometimes', 'string', 'in:daily,weekly,shift'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
