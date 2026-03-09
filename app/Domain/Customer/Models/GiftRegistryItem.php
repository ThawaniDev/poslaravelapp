<?php

namespace App\Domain\Customer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftRegistryItem extends Model
{
    use HasUuids;

    protected $table = 'gift_registry_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'registry_id',
        'product_id',
        'quantity_desired',
        'quantity_purchased',
        'purchased_by_name',
    ];

    public function registry(): BelongsTo
    {
        return $this->belongsTo(GiftRegistry::class, 'registry_id');
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
