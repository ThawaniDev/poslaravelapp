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
            'receipt_show_address' => ['sometimes', 'boolean'],
            'receipt_show_phone' => ['sometimes', 'boolean'],
            'receipt_show_date' => ['sometimes', 'boolean'],
            'receipt_show_cashier' => ['sometimes', 'boolean'],
            'receipt_show_barcode' => ['sometimes', 'boolean'],
            'receipt_paper_size' => ['sometimes', 'string', 'in:58mm,80mm'],
            'receipt_font_size' => ['sometimes', 'string', 'in:small,normal,large'],
            'receipt_language' => ['sometimes', 'string', 'in:ar,en,both'],
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
            'barcode_scan_sound' => ['sometimes', 'boolean'],
            'default_sale_type' => ['sometimes', 'string', 'in:dine_in,takeaway,delivery'],
            'enable_hold_orders' => ['sometimes', 'boolean'],
            'enable_refunds' => ['sometimes', 'boolean'],
            'enable_exchanges' => ['sometimes', 'boolean'],
            'require_manager_for_refund' => ['sometimes', 'boolean'],
            'require_manager_for_discount' => ['sometimes', 'boolean'],
            'enable_open_price_items' => ['sometimes', 'boolean'],
            'enable_quick_add_products' => ['sometimes', 'boolean'],
            // Loyalty
            'enable_loyalty_points' => ['sometimes', 'boolean'],
            'loyalty_points_per_currency' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'loyalty_redemption_value' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            // Notifications
            'low_stock_alert' => ['sometimes', 'boolean'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            // Inventory
            'track_inventory' => ['sometimes', 'boolean'],
            'enable_batch_tracking' => ['sometimes', 'boolean'],
            'enable_expiry_tracking' => ['sometimes', 'boolean'],
            'auto_deduct_ingredients' => ['sometimes', 'boolean'],
            // Display
            'theme_mode' => ['sometimes', 'string', 'in:light,dark,system'],
            'display_language' => ['sometimes', 'string', 'in:ar,en'],
            // Customer display
            'enable_customer_display' => ['sometimes', 'boolean'],
            'customer_display_message' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Extra
            'extra' => ['sometimes', 'array'],
        ];
    }
}
