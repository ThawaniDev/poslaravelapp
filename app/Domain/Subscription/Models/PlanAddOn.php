<?php

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\ProviderSubscription\Models\StoreAddOn;

class PlanAddOn extends Model
{
    use HasUuids;

    protected $table = 'plan_add_ons';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'monthly_price',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
    ];

    public function storeAddOns(): HasMany
    {
        return $this->hasMany(StoreAddOn::class);
    }
}
