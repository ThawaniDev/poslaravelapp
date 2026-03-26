<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\ContentOnboarding\Enums\PurchaseType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplatePurchase extends Model
{
    use HasUuids;

    protected $table = 'template_purchases';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'store_id', 'marketplace_listing_id', 'purchase_type',
        'amount_paid', 'currency', 'payment_reference', 'payment_gateway',
        'subscription_starts_at', 'subscription_expires_at', 'auto_renew',
        'is_active', 'cancelled_at', 'refunded_at', 'invoice_id',
    ];

    protected $casts = [
        'purchase_type' => PurchaseType::class,
        'amount_paid' => 'decimal:2',
        'auto_renew' => 'boolean',
        'is_active' => 'boolean',
        'subscription_starts_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Store\Models\Store::class, 'store_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(TemplateMarketplaceListing::class, 'marketplace_listing_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MarketplacePurchaseInvoice::class, 'invoice_id');
    }
}
