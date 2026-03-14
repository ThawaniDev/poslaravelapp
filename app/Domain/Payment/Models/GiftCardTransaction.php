<?php

namespace App\Domain\Payment\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\GiftCardTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTransaction extends Model
{
    use HasUuids;

    protected $table = 'gift_card_transactions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'gift_card_id',
        'type',
        'amount',
        'balance_after',
        'payment_id',
        'store_id',
        'performed_by',
    ];

    protected $casts = [
        'type' => GiftCardTransactionType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
