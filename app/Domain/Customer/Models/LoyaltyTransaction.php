<?php

namespace App\Domain\Customer\Models;

use App\Domain\Customer\Enums\LoyaltyTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use HasUuids;

    protected $table = 'loyalty_transactions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'type',
        'points',
        'balance_after',
        'order_id',
        'notes',
        'performed_by',
        'created_at',
    ];

    protected $casts = [
        'type' => LoyaltyTransactionType::class,
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
