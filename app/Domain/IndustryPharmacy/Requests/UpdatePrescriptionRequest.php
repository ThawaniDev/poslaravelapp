<?php

namespace App\Domain\IndustryPharmacy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'insurance_provider'     => ['nullable', 'string', 'max:255'],
            'insurance_claim_amount' => ['nullable', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
