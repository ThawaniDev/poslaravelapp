<?php

namespace App\Domain\ProviderPayment\Models;

use App\Domain\Core\Models\Organization;
use App\Domain\ProviderPayment\Enums\PaymentPurpose;
use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderSubscription\Models\Invoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderPayment extends Model
{
    use HasUuids;

    protected $table = 'provider_payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'purpose',
        'purpose_label',
        'purpose_reference_id',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'original_currency',
        'original_amount',
        'exchange_rate_used',
        'gateway',
        'tran_ref',
        'tran_type',
        'cart_id',
        'status',
        'response_status',
        'response_code',
        'response_message',
        'card_type',
        'card_scheme',
        'payment_description',
        'payment_method',
        'token',
        'previous_tran_ref',
        'confirmation_email_sent',
        'confirmation_email_sent_at',
        'confirmation_email_error',
        'invoice_generated',
        'invoice_generated_at',
        'ipn_received',
        'ipn_received_at',
        'ipn_payload',
        'refund_amount',
        'refund_tran_ref',
        'refunded_at',
        'refund_reason',
        'gateway_response',
        'payment_context',
        'customer_details',
        'notes',
        'initiated_by',
    ];

    protected $casts = [
        'purpose' => PaymentPurpose::class,
        'status' => ProviderPaymentStatus::class,
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate_used' => 'decimal:6',
        'refund_amount' => 'decimal:2',
        'confirmation_email_sent' => 'boolean',
        'confirmation_email_sent_at' => 'datetime',
        'invoice_generated' => 'boolean',
        'invoice_generated_at' => 'datetime',
        'ipn_received' => 'boolean',
        'ipn_received_at' => 'datetime',
        'ipn_payload' => 'array',
        'refunded_at' => 'datetime',
        'gateway_response' => 'array',
        'payment_context' => 'array',
        'customer_details' => 'array',
    ];

    // ─── Relationships ──────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function initiatedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'initiated_by');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(PaymentEmailLog::class);
    }

    // ─── Helpers ────────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->status === ProviderPaymentStatus::Completed;
    }

    public function isPending(): bool
    {
        return $this->status === ProviderPaymentStatus::Pending;
    }

    public function canRefund(): bool
    {
        return $this->status === ProviderPaymentStatus::Completed && $this->refund_amount === null;
    }

    public function getFormattedAmount(): string
    {
        return number_format((float) $this->total_amount, 2) . ' ' . $this->currency;
    }

    public function hasOriginalCurrency(): bool
    {
        return $this->original_currency !== null && $this->original_currency !== $this->currency;
    }

    public function getFormattedOriginalAmount(): ?string
    {
        if (! $this->hasOriginalCurrency()) {
            return null;
        }

        return number_format((float) $this->original_amount, 2) . ' ' . $this->original_currency;
    }
}
