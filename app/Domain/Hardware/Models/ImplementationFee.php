<?php

namespace App\Domain\Hardware\Models;

use App\Domain\Hardware\Enums\ImplementationFeeStatus;
use App\Domain\Hardware\Enums\ImplementationFeeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImplementationFee extends Model
{
    use HasUuids;

    protected $table = 'implementation_fees';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'fee_type',
        'amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'fee_type' => ImplementationFeeType::class,
        'status' => ImplementationFeeStatus::class,
        'amount' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
