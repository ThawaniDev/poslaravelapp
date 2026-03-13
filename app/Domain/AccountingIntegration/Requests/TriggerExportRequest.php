<?php

namespace App\Domain\AccountingIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'export_types' => 'nullable|array',
            'export_types.*' => 'string|in:daily_summary,payment_breakdown,category_breakdown,expense_entries,payroll_summary,full_reconciliation',
        ];
    }
}
