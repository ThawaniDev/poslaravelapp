<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\PaymentMethodKey;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUuids;

    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'method',
        'amount',
        'cash_tendered',
        'change_given',
        'tip_amount',
        'card_brand',
        'card_last_four',
        'card_auth_code',
        'card_reference',
        'gift_card_code',
        'coupon_code',
        'loyalty_points_used',
    ];

    protected $casts = [
        'method' => PaymentMethodKey::class,
        'amount' => 'decimal:2',
        'cash_tendered' => 'decimal:2',
        'change_given' => 'decimal:2',
        'tip_amount' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
    public function giftCardTransactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
