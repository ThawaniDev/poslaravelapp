<?php

namespace App\Domain\AccountingIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutoExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'nullable|boolean',
            'frequency' => 'nullable|string|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'time' => 'nullable|date_format:H:i',
            'export_types' => 'nullable|array',
            'export_types.*' => 'string|in:daily_summary,payment_breakdown,category_breakdown,expense_entries,payroll_summary,full_reconciliation',
            'notify_email' => 'nullable|email|max:255',
            'retry_on_failure' => 'nullable|boolean',
        ];
    }
}
