<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            // Tax
            'tax_label' => $this->tax_label,
            'tax_rate' => (float) $this->tax_rate,
            'prices_include_tax' => $this->prices_include_tax,
            'tax_number' => $this->tax_number,
            // Receipt
            'receipt_header' => $this->receipt_header,
            'receipt_footer' => $this->receipt_footer,
            'receipt_show_logo' => $this->receipt_show_logo,
            'receipt_show_tax_breakdown' => $this->receipt_show_tax_breakdown,
            'receipt_show_address' => $this->receipt_show_address,
            'receipt_show_phone' => $this->receipt_show_phone,
            'receipt_show_date' => $this->receipt_show_date,
            'receipt_show_cashier' => $this->receipt_show_cashier,
            'receipt_show_barcode' => $this->receipt_show_barcode,
            'receipt_paper_size' => $this->receipt_paper_size,
            'receipt_font_size' => $this->receipt_font_size,
            'receipt_language' => $this->receipt_language,
            // Currency
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->currency_symbol,
            'decimal_places' => $this->decimal_places,
            'thousand_separator' => $this->thousand_separator,
            'decimal_separator' => $this->decimal_separator,
            // POS behaviour
            'allow_negative_stock' => $this->allow_negative_stock,
            'require_customer_for_sale' => $this->require_customer_for_sale,
            'auto_print_receipt' => $this->auto_print_receipt,
            'session_timeout_minutes' => $this->session_timeout_minutes,
            'max_discount_percent' => $this->max_discount_percent,
            'enable_tips' => $this->enable_tips,
            'enable_kitchen_display' => $this->enable_kitchen_display,
            'barcode_scan_sound' => $this->barcode_scan_sound,
            'default_sale_type' => $this->default_sale_type,
            'enable_hold_orders' => $this->enable_hold_orders,
            'enable_refunds' => $this->enable_refunds,
            'enable_exchanges' => $this->enable_exchanges,
            'require_manager_for_refund' => $this->require_manager_for_refund,
            'require_manager_for_discount' => $this->require_manager_for_discount,
            'enable_open_price_items' => $this->enable_open_price_items,
            'enable_quick_add_products' => $this->enable_quick_add_products,
            // Loyalty
            'enable_loyalty_points' => $this->enable_loyalty_points,
            'loyalty_points_per_currency' => (float) $this->loyalty_points_per_currency,
            'loyalty_redemption_value' => (float) $this->loyalty_redemption_value,
            // Notifications
            'low_stock_alert' => $this->low_stock_alert,
            'low_stock_threshold' => $this->low_stock_threshold,
            // Inventory tracking
            'track_inventory' => $this->track_inventory,
            'enable_batch_tracking' => $this->enable_batch_tracking,
            'enable_expiry_tracking' => $this->enable_expiry_tracking,
            'auto_deduct_ingredients' => $this->auto_deduct_ingredients,
            // Display
            'theme_mode' => $this->theme_mode,
            'display_language' => $this->display_language,
            // Customer display
            'enable_customer_display' => $this->enable_customer_display,
            'customer_display_message' => $this->customer_display_message,
            // Extra
            'extra' => $this->extra ?? [],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
