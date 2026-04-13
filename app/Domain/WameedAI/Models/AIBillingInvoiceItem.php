<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIBillingInvoiceItem extends Model
{
    use HasUuids;

    protected $table = 'ai_billing_invoice_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ai_billing_invoice_id',
        'feature_slug',
        'feature_name',
        'feature_name_ar',
        'request_count',
        'total_tokens',
        'raw_cost_usd',
        'billed_cost_usd',
        'created_at',
    ];

    protected $casts = [
        'request_count' => 'integer',
        'total_tokens' => 'integer',
        'raw_cost_usd' => 'decimal:5',
        'billed_cost_usd' => 'decimal:5',
        'created_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AIBillingInvoice::class, 'ai_billing_invoice_id');
    }
}
