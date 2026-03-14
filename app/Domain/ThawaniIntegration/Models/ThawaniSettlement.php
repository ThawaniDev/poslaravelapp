<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniSettlement extends Model
{
    use HasUuids;

    protected $table = 'thawani_settlements';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'settlement_date',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'order_count',
        'thawani_reference',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'settlement_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
