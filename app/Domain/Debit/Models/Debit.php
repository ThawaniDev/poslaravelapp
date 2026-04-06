<?php

namespace App\Domain\Debit\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\Debit\Enums\DebitSource;
use App\Domain\Debit\Enums\DebitStatus;
use App\Domain\Debit\Enums\DebitType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debit extends Model
{
    use HasUuids;

    protected $table = 'debits';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'store_id',
        'customer_id',
        'reference_number',
        'debit_type',
        'source',
        'description',
        'description_ar',
        'amount',
        'status',
        'notes',
        'created_by',
        'allocated_by',
        'allocated_at',
        'sync_version',
    ];

    protected $casts = [
        'debit_type' => DebitType::class,
        'source' => DebitSource::class,
        'status' => DebitStatus::class,
        'amount' => 'decimal:2',
        'allocated_at' => 'datetime',
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

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DebitAllocation::class);
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DebitStatus::Pending,
            DebitStatus::PartiallyAllocated,
        ]);
    }

    public function scopeUnallocated(Builder $query): Builder
    {
        return $query->where('status', DebitStatus::Pending);
    }

    public function scopeByCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    // ─── Computed ──────────────────────────────────────────────

    public function getRemainingBalanceAttribute(): float
    {
        $allocated = $this->allocations()->sum('amount');

        return round((float) $this->amount - (float) $allocated, 2);
    }

    public function isFullyAllocated(): bool
    {
        return $this->remaining_balance <= 0.009;
    }
}
