<?php

namespace App\Domain\Customer\Models;

use App\Domain\ContentOnboarding\Enums\GiftRegistryEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftRegistry extends Model
{
    use HasUuids;

    protected $table = 'gift_registries';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'customer_id',
        'name',
        'event_type',
        'event_date',
        'share_code',
        'is_active',
    ];

    protected $casts = [
        'event_type' => GiftRegistryEventType::class,
        'is_active' => 'boolean',
        'event_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function giftRegistryItems(): HasMany
    {
        return $this->hasMany(GiftRegistryItem::class, 'registry_id');
    }
}
