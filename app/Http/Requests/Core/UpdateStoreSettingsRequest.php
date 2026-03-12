<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Tax
            'tax_label' => ['sometimes', 'string', 'max:50'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'prices_include_tax' => ['sometimes', 'boolean'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            // Receipt
            'receipt_header' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt_show_logo' => ['sometimes', 'boolean'],
            'receipt_show_tax_breakdown' => ['sometimes', 'boolean'],
            // Currency
            'currency_code' => ['sometimes', 'string', 'max:10'],
            'currency_symbol' => ['sometimes', 'string', 'max:5'],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'thousand_separator' => ['sometimes', 'string', 'max:3'],
            'decimal_separator' => ['sometimes', 'string', 'max:3'],
            // POS behaviour
            'allow_negative_stock' => ['sometimes', 'boolean'],
            'require_customer_for_sale' => ['sometimes', 'boolean'],
            'auto_print_receipt' => ['sometimes', 'boolean'],
            'session_timeout_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'max_discount_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'enable_tips' => ['sometimes', 'boolean'],
            'enable_kitchen_display' => ['sometimes', 'boolean'],
            // Notifications
            'low_stock_alert' => ['sometimes', 'boolean'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            // Extra
            'extra' => ['sometimes', 'array'],
        ];
    }
}
