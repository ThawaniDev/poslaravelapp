<?php

namespace App\Domain\IndustryJewelry\Models;

use App\Domain\IndustryJewelry\Enums\BuybackPaymentMethod;
use App\Domain\IndustryJewelry\Enums\MetalType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuybackTransaction extends Model
{
    use HasUuids;

    protected $table = 'buyback_transactions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'customer_id',
        'metal_type',
        'karat',
        'weight_g',
        'rate_per_gram',
        'total_amount',
        'payment_method',
        'staff_user_id',
        'notes',
    ];

    protected $casts = [
        'metal_type' => MetalType::class,
        'payment_method' => BuybackPaymentMethod::class,
        'weight_g' => 'decimal:2',
        'rate_per_gram' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
