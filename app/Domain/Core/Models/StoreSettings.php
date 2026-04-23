<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSettings extends Model
{
    use HasUuids;

    protected $table = 'store_settings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        // Tax
        'tax_label',
        'tax_rate',
        'prices_include_tax',
        'tax_number',
        // Receipt
        'receipt_header',
        'receipt_footer',
        'receipt_show_logo',
        'receipt_show_tax_breakdown',
        'receipt_show_address',
        'receipt_show_phone',
        'receipt_show_date',
        'receipt_show_cashier',
        'receipt_show_barcode',
        'receipt_paper_size',
        'receipt_font_size',
        'receipt_language',
        // Currency
        'currency_code',
        'currency_symbol',
        'decimal_places',
        'thousand_separator',
        'decimal_separator',
        // POS behaviour
        'allow_negative_stock',
        'require_customer_for_sale',
        'auto_print_receipt',
        'session_timeout_minutes',
        'max_discount_percent',
        'enable_tips',
        'enable_kitchen_display',
        'barcode_scan_sound',
        'default_sale_type',
        'enable_hold_orders',
        'held_cart_expiry_hours',
        'enable_refunds',
        'return_without_receipt_policy',
        'enable_exchanges',
        'require_manager_for_refund',
        'require_manager_for_discount',
        'enable_open_price_items',
        'enable_quick_add_products',
        // Loyalty
        'enable_loyalty_points',
        'loyalty_points_per_currency',
        'loyalty_redemption_value',
        // Notifications
        'low_stock_alert',
        'low_stock_threshold',
        // Inventory tracking
        'track_inventory',
        'enable_batch_tracking',
        'enable_expiry_tracking',
        'auto_deduct_ingredients',
        // Display
        'theme_mode',
        'display_language',
        // Customer display
        'enable_customer_display',
        'customer_display_message',
        // Extra
        'extra',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'prices_include_tax' => 'boolean',
        'receipt_show_logo' => 'boolean',
        'receipt_show_tax_breakdown' => 'boolean',
        'receipt_show_address' => 'boolean',
        'receipt_show_phone' => 'boolean',
        'receipt_show_date' => 'boolean',
        'receipt_show_cashier' => 'boolean',
        'receipt_show_barcode' => 'boolean',
        'decimal_places' => 'integer',
        'allow_negative_stock' => 'boolean',
        'require_customer_for_sale' => 'boolean',
        'auto_print_receipt' => 'boolean',
        'session_timeout_minutes' => 'integer',
        'max_discount_percent' => 'integer',
        'enable_tips' => 'boolean',
        'enable_kitchen_display' => 'boolean',
        'barcode_scan_sound' => 'boolean',
        'enable_hold_orders' => 'boolean',
        'enable_refunds' => 'boolean',
        'enable_exchanges' => 'boolean',
        'require_manager_for_refund' => 'boolean',
        'require_manager_for_discount' => 'boolean',
        'enable_open_price_items' => 'boolean',
        'enable_quick_add_products' => 'boolean',
        'enable_loyalty_points' => 'boolean',
        'loyalty_points_per_currency' => 'decimal:2',
        'loyalty_redemption_value' => 'decimal:2',
        'low_stock_alert' => 'boolean',
        'track_inventory' => 'boolean',
        'enable_batch_tracking' => 'boolean',
        'enable_expiry_tracking' => 'boolean',
        'auto_deduct_ingredients' => 'boolean',
        'enable_customer_display' => 'boolean',
        'low_stock_threshold' => 'integer',
        'extra' => 'array',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Get a setting from the extra JSON bag.
     */
    public function getExtra(string $key, mixed $default = null): mixed
    {
        return data_get($this->extra, $key, $default);
    }

    /**
     * Set a value in the extra JSON bag.
     */
    public function setExtra(string $key, mixed $value): static
    {
        $extra = $this->extra ?? [];
        data_set($extra, $key, $value);
        $this->extra = $extra;
        return $this;
    }
}
