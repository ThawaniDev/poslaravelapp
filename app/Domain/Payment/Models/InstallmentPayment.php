<?php

namespace App\Domain\Payment\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\InstallmentPaymentStatus;
use App\Domain\Payment\Enums\InstallmentProvider;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPayment extends Model
{
    use HasUuids;

    protected $table = 'installment_payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'transaction_id',
        'payment_id',
        'provider',
        'provider_order_id',
        'provider_checkout_id',
        'provider_payment_id',
        'amount',
        'currency',
        'installment_count',
        'status',
        'checkout_url',
        'customer_name',
        'customer_phone',
        'customer_email',
        'provider_response',
        'error_code',
        'error_message',
        'initiated_at',
        'completed_at',
        'cancelled_at',
        'expired_at',
    ];

    protected $casts = [
        'provider' => InstallmentProvider::class,
        'status' => InstallmentPaymentStatus::class,
        'amount' => 'decimal:2',
        'installment_count' => 'integer',
        'provider_response' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePending($query)
    {
        return $query->where('status', InstallmentPaymentStatus::Pending);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', InstallmentPaymentStatus::Completed);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function markCompleted(array $providerData = []): void
    {
        $this->update([
            'status' => InstallmentPaymentStatus::Completed,
            'completed_at' => now(),
            'provider_response' => array_merge($this->provider_response ?? [], $providerData),
        ]);
    }

    public function markFailed(string $errorCode = null, string $errorMessage = null): void
    {
        $this->update([
            'status' => InstallmentPaymentStatus::Failed,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => InstallmentPaymentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
