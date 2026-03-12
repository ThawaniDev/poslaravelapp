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
        // Notifications
        'low_stock_alert',
        'low_stock_threshold',
        // Extra
        'extra',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'prices_include_tax' => 'boolean',
        'receipt_show_logo' => 'boolean',
        'receipt_show_tax_breakdown' => 'boolean',
        'decimal_places' => 'integer',
        'allow_negative_stock' => 'boolean',
        'require_customer_for_sale' => 'boolean',
        'auto_print_receipt' => 'boolean',
        'session_timeout_minutes' => 'integer',
        'max_discount_percent' => 'integer',
        'enable_tips' => 'boolean',
        'enable_kitchen_display' => 'boolean',
        'low_stock_alert' => 'boolean',
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
