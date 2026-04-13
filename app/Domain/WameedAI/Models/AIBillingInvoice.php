<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIBillingInvoice extends Model
{
    use HasUuids;

    protected $table = 'ai_billing_invoices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'organization_id',
        'invoice_number',
        'year',
        'month',
        'period_start',
        'period_end',
        'total_requests',
        'total_tokens',
        'raw_cost_usd',
        'margin_percentage',
        'margin_amount_usd',
        'billed_amount_usd',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        'payment_notes',
        'generated_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_requests' => 'integer',
        'total_tokens' => 'integer',
        'raw_cost_usd' => 'decimal:5',
        'margin_percentage' => 'decimal:3',
        'margin_amount_usd' => 'decimal:5',
        'billed_amount_usd' => 'decimal:5',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class, 'store_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Organization::class, 'organization_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AIBillingInvoiceItem::class, 'ai_billing_invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AIBillingPayment::class, 'ai_billing_invoice_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_usd');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->billed_amount_usd - $this->total_paid);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public static function generateInvoiceNumber(string $storeId, int $year, int $month): string
    {
        $storeShort = strtoupper(substr(str_replace('-', '', $storeId), -8));
        return sprintf('AI-%s-%04d%02d', $storeShort, $year, $month);
    }
}
