<?php

namespace App\Domain\IndustryFlorist\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowerArrangement extends Model
{
    use HasUuids;

    protected $table = 'flower_arrangements';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'name',
        'occasion',
        'items_json',
        'total_price',
        'is_template',
    ];

    protected $casts = [
        'items_json' => 'array',
        'is_template' => 'boolean',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function flowerSubscriptions(): HasMany
    {
        return $this->hasMany(FlowerSubscription::class, 'arrangement_template_id');
    }
}
