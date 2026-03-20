<?php

namespace App\Domain\IndustryElectronics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRepairJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'        => ['nullable', 'uuid'],
            'device_description' => ['required', 'string', 'max:255'],
            'imei'               => ['nullable', 'string', 'max:20'],
            'issue_description'  => ['required', 'string', 'max:2000'],
            'estimated_cost'     => ['nullable', 'numeric', 'min:0'],
            'estimated_ready_at' => ['nullable', 'date'],
        ];
    }
}
