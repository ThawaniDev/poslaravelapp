<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Promotion\Enums\DiscountType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SubscriptionDiscount extends Model
{
    use HasUuids;

    protected $table = 'subscription_discounts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'times_used',
        'valid_from',
        'valid_to',
        'applicable_plan_ids',
    ];

    protected $casts = [
        'type' => DiscountType::class,
        'applicable_plan_ids' => 'array',
        'value' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

}
