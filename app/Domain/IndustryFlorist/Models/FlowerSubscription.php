<?php

namespace App\Domain\IndustryFlorist\Models;

use App\Domain\IndustryFlorist\Enums\FlowerSubscriptionFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowerSubscription extends Model
{
    use HasUuids;

    protected $table = 'flower_subscriptions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'customer_id',
        'arrangement_template_id',
        'frequency',
        'delivery_day',
        'delivery_address',
        'price_per_delivery',
        'is_active',
        'next_delivery_date',
    ];

    protected $casts = [
        'frequency' => FlowerSubscriptionFrequency::class,
        'is_active' => 'boolean',
        'price_per_delivery' => 'decimal:2',
        'next_delivery_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function arrangementTemplate(): BelongsTo
    {
        return $this->belongsTo(FlowerArrangement::class, 'arrangement_template_id');
    }
}
