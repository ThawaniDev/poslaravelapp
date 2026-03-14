<?php

namespace App\Domain\Payment\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\GiftCardStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    use HasUuids;

    protected $table = 'gift_cards';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'code',
        'barcode',
        'initial_amount',
        'balance',
        'recipient_name',
        'status',
        'issued_by',
        'issued_at_store',
        'expires_at',
    ];

    protected $casts = [
        'status' => GiftCardStatus::class,
        'initial_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'expires_at' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
    public function issuedAtStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'issued_at_store');
    }
    public function giftCardTransactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }
}
