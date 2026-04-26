<?php

namespace App\Domain\Report\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', 'in:sales_summary,product_performance,category_breakdown,staff_performance,slow_movers,product_margin,inventory_valuation,inventory_low_stock,inventory_expiry,financial_pl,financial_expenses,financial_delivery_commission,top_customers'],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly'],
            'filters' => ['sometimes', 'array'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['email'],
            'format' => ['sometimes', 'string', 'in:pdf,csv'],
        ];
    }
}
