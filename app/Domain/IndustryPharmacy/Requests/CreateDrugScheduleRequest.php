<?php

namespace App\Domain\IndustryPharmacy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDrugScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'            => ['required', 'uuid'],
            'schedule_type'         => ['required', 'string', 'in:otc,behind_counter,prescription_only,controlled'],
            'active_ingredient'     => ['required', 'string', 'max:255'],
            'dosage_form'           => ['required', 'string', 'max:100'],
            'strength'              => ['required', 'string', 'max:100'],
            'manufacturer'          => ['nullable', 'string', 'max:255'],
            'requires_prescription' => ['required', 'boolean'],
        ];
    }
}
