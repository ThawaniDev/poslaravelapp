<?php

namespace App\Domain\PredefinedCatalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredefinedProductImage extends Model
{
    use HasUuids;

    protected $table = 'predefined_product_images';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'predefined_product_id',
        'image_url',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(PredefinedProduct::class, 'predefined_product_id');
    }
}
