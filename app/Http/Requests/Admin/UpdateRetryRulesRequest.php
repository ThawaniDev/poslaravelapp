<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRetryRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_retries' => 'required|integer|min:1|max:10',
            'retry_interval_hours' => 'required|integer|min:1|max:168',
            'grace_period_after_failure_days' => 'required|integer|min:1|max:30',
        ];
    }
}
