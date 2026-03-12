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
            // Notifications
            'low_stock_alert' => $this->low_stock_alert,
            'low_stock_threshold' => $this->low_stock_threshold,
            // Extra
            'extra' => $this->extra ?? [],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
