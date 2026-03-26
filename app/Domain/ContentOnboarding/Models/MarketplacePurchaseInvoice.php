<?php

namespace App\Domain\ContentOnboarding\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePurchaseInvoice extends Model
{
    use HasUuids;

    protected $table = 'marketplace_purchase_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'template_purchase_id',
        'invoice_number',
        'status',
        'store_id',
        'seller_name',
        'seller_email',
        'seller_vat_number',
        'buyer_store_name',
        'buyer_organization_name',
        'buyer_vat_number',
        'buyer_email',
        'item_description',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'payment_method',
        'payment_reference',
        'paid_at',
        'billing_period',
        'is_recurring',
        'notes',
        'notes_ar',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(TemplatePurchase::class, 'template_purchase_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
