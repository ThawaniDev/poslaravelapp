<?php

namespace App\Domain\ZatcaCompliance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'size:6'],
            'environment' => ['required', 'string', 'in:simulation,production'],
        ];
    }
}
