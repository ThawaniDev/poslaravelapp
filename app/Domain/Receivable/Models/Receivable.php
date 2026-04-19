<?php

namespace App\Domain\Receivable\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Receivable\Enums\ReceivableSource;
use App\Domain\Receivable\Enums\ReceivableStatus;
use App\Domain\Receivable\Enums\ReceivableType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    use HasUuids;

    protected $table = 'receivables';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'store_id',
        'customer_id',
        'reference_number',
        'receivable_type',
        'source',
        'description',
        'description_ar',
        'amount',
        'status',
        'due_date',
        'notes',
        'created_by',
        'settled_by',
        'settled_at',
        'sync_version',
    ];

    protected $casts = [
        'receivable_type' => ReceivableType::class,
        'source' => ReceivableSource::class,
        'status' => ReceivableStatus::class,
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'settled_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReceivableLog::class)->orderByDesc('created_at');
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReceivableStatus::Pending,
            ReceivableStatus::PartiallyPaid,
        ]);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', ReceivableStatus::Pending);
    }

    public function scopeByCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    // ─── Computed ──────────────────────────────────────────────

    public function getRemainingBalanceAttribute(): float
    {
        $allocated = $this->payments()->sum('amount');

        return round((float) $this->amount - (float) $allocated, 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->remaining_balance <= 0.009;
    }
}
