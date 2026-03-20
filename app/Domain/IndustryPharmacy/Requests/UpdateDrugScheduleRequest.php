<?php

namespace App\Domain\IndustryPharmacy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDrugScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_type'        => ['sometimes', 'string', 'in:otc,prescription_only,controlled'],
            'active_ingredient'    => ['nullable', 'string', 'max:255'],
            'dosage_form'          => ['nullable', 'string', 'max:100'],
            'strength'             => ['nullable', 'string', 'max:100'],
            'manufacturer'         => ['nullable', 'string', 'max:255'],
            'requires_prescription' => ['sometimes', 'boolean'],
        ];
    }
}
