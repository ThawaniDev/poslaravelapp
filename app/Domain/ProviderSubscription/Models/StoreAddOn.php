<?php

namespace App\Domain\ProviderSubscription\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StoreAddOn extends Pivot
{
    protected $table = 'store_add_ons';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'plan_add_on_id',
        'activated_at',
        'deactivated_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function planAddOn(): BelongsTo
    {
        return $this->belongsTo(PlanAddOn::class);
    }
}
