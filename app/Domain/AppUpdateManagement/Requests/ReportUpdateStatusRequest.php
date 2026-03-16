<?php

namespace App\Domain\AppUpdateManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportUpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'release_id' => 'required|string|uuid',
            'status' => 'required|in:pending,downloading,downloaded,installed,failed',
            'error_message' => 'nullable|string|max:500',
        ];
    }
}
