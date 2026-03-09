<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\PaymentMethodKey;
use App\Domain\Payment\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUuids;

    protected $table = 'refunds';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'return_id',
        'payment_id',
        'method',
        'amount',
        'reference_number',
        'status',
        'processed_by',
    ];

    protected $casts = [
        'method' => PaymentMethodKey::class,
        'status' => RefundStatus::class,
        'amount' => 'decimal:2',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
