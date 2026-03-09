<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyBadge extends Model
{
    use HasUuids;

    protected $table = 'loyalty_badges';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'name_ar',
        'name_en',
        'icon_url',
        'description_ar',
        'description_en',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customerBadges(): HasMany
    {
        return $this->hasMany(CustomerBadge::class, 'badge_id');
    }
}
