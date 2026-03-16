<?php

namespace App\Domain\AppUpdateManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_version' => 'required|string|max:20',
            'platform' => 'required|in:windows,macos,ios,android',
            'channel' => 'sometimes|in:stable,beta,testflight,internal_test',
        ];
    }
}
