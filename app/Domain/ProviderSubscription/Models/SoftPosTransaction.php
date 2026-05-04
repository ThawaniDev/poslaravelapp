<?php

namespace App\Domain\ProviderSubscription\Models;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Register;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoftPosTransaction extends Model
{
    use HasUuids;

    protected $table = 'softpos_transactions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'store_id',
        'order_id',
        'amount',
        'currency',
        // Fee tracking (bilateral)
        'platform_fee',
        'gateway_fee',
        'margin',
        'fee_type',
        // Transaction details
        'transaction_ref',
        'payment_method',
        'terminal_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount'       => 'decimal:3',
        'platform_fee' => 'decimal:3',
        'gateway_fee'  => 'decimal:3',
        'margin'       => 'decimal:3',
        'metadata'     => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Register::class, 'terminal_id');
    }
}
