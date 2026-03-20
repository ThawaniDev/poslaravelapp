<?php

namespace App\Domain\IndustryElectronics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRepairJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'diagnosis_notes'    => ['nullable', 'string', 'max:2000'],
            'repair_notes'       => ['nullable', 'string', 'max:2000'],
            'parts_used'         => ['nullable', 'array'],
            'estimated_cost'     => ['nullable', 'numeric', 'min:0'],
            'final_cost'         => ['nullable', 'numeric', 'min:0'],
            'estimated_ready_at' => ['nullable', 'date'],
        ];
    }
}
