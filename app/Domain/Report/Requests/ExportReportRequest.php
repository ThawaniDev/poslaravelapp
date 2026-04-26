<?php

namespace App\Domain\Report\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', 'in:sales_summary,product_performance,category_breakdown,staff_performance,slow_movers,product_margin,inventory_valuation,inventory_low_stock,inventory_expiry,financial_pl,financial_expenses,financial_delivery_commission,top_customers'],
            'format' => ['required', 'string', 'in:pdf,csv'],
            'date_from' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category_id' => ['sometimes', 'uuid'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
