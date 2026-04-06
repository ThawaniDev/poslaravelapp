<?php

namespace App\Domain\Debit\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitAllocation extends Model
{
    use HasUuids;

    protected $table = 'debit_allocations';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'debit_id',
        'order_id',
        'amount',
        'notes',
        'allocated_by',
        'allocated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function debit(): BelongsTo
    {
        return $this->belongsTo(Debit::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
