<?php

namespace App\Domain\PosTerminal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeTransaction extends Model
{
    use HasUuids;

    protected $table = 'exchange_transactions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'return_transaction_id',
        'sale_transaction_id',
        'net_amount',
    ];

    protected $casts = [
        'net_amount' => 'decimal:2',
    ];

    public function returnTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'return_transaction_id');
    }
    public function saleTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'sale_transaction_id');
    }
}
