<?php

namespace App\Domain\Customer\Models;

use App\Domain\Customer\Enums\StoreCreditTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCreditTransaction extends Model
{
    use HasUuids;

    protected $table = 'store_credit_transactions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'balance_after',
        'order_id',
        'payment_id',
        'notes',
        'performed_by',
        'created_at',
    ];

    protected $casts = [
        'type' => StoreCreditTransactionType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
