<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIBillingPayment extends Model
{
    use HasUuids;

    protected $table = 'ai_billing_payments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ai_billing_invoice_id',
        'amount_usd',
        'payment_method',
        'reference',
        'notes',
        'recorded_by',
        'created_at',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:5',
        'created_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AIBillingInvoice::class, 'ai_billing_invoice_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'recorded_by');
    }
}
