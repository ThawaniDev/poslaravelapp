<?php

namespace App\Domain\Report\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category_id' => ['sometimes', 'uuid'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'compare' => ['sometimes', 'boolean'],

            // Advanced filters
            'branch_id' => ['sometimes', 'uuid'],
            'staff_id' => ['sometimes', 'uuid'],
            'payment_method' => ['sometimes', 'string', 'in:cash,card,gift_card,mobile,bank_transfer'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'numeric', 'min:0', 'gte:min_amount'],
            'order_status' => ['sometimes', 'string', 'in:completed,refunded,partially_refunded'],
            'sort_by' => ['sometimes', 'string', 'in:revenue,quantity,profit,orders,date,name'],
            'sort_dir' => ['sometimes', 'string', 'in:asc,desc'],
            'granularity' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'product_id' => ['sometimes', 'uuid'],
        ];
    }
}
