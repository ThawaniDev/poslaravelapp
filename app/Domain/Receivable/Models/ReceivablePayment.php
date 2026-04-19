<?php

namespace App\Domain\Receivable\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePayment extends Model
{
    use HasUuids;

    protected $table = 'receivable_payments';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'receivable_id',
        'order_id',
        'payment_method',
        'amount',
        'notes',
        'settled_by',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
