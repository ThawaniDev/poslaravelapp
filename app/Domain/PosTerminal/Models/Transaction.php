<?php

namespace App\Domain\PosTerminal\Models;

use App\Domain\Order\Enums\ExternalOrderType;
use App\Domain\Payment\Models\Payment;
use App\Domain\ThawaniIntegration\Enums\SyncStatus;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\ZatcaCompliance\Enums\ZatcaComplianceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'transactions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'store_id',
        'register_id',
        'pos_session_id',
        'cashier_id',
        'customer_id',
        'transaction_number',
        'type',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'tip_amount',
        'total_amount',
        'is_tax_exempt',
        'return_transaction_id',
        'external_type',
        'external_id',
        'notes',
        'zatca_uuid',
        'zatca_hash',
        'zatca_qr_code',
        'zatca_status',
        'sync_status',
        'sync_version',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'external_type' => ExternalOrderType::class,
        'zatca_status' => ZatcaComplianceStatus::class,
        'sync_status' => SyncStatus::class,
        'is_tax_exempt' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function returnTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'return_transaction_id');
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'return_transaction_id');
    }
    /**
     * Alias of {@see transactions()} scoped to refund/return transactions issued
     * against this sale. Used by eager loads and the refunded_quantities payload.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(Transaction::class, 'return_transaction_id');
    }
    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
    public function exchangeTransactions(): HasMany
    {
        return $this->hasMany(ExchangeTransaction::class, 'return_transaction_id');
    }
    public function exchangeTransactionsViaSaleTransaction(): HasMany
    {
        return $this->hasMany(ExchangeTransaction::class, 'sale_transaction_id');
    }
    public function taxExemptions(): HasMany
    {
        return $this->hasMany(TaxExemption::class);
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
