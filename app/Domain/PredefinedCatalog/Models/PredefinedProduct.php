<?php

namespace App\Domain\PredefinedCatalog\Models;

use App\Domain\Catalog\Enums\ProductUnit;
use App\Domain\ContentOnboarding\Models\BusinessType as BusinessTypeModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredefinedProduct extends Model
{
    use HasUuids;

    protected $table = 'predefined_products';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_type_id',
        'predefined_category_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'sku',
        'barcode',
        'sell_price',
        'cost_price',
        'unit',
        'tax_rate',
        'is_weighable',
        'tare_weight',
        'is_active',
        'age_restricted',
        'image_url',
    ];

    protected $casts = [
        'unit' => ProductUnit::class,
        'is_weighable' => 'boolean',
        'is_active' => 'boolean',
        'age_restricted' => 'boolean',
        'sell_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tare_weight' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessTypeModel::class);
    }

    public function predefinedCategory(): BelongsTo
    {
        return $this->belongsTo(PredefinedCategory::class, 'predefined_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PredefinedProductImage::class, 'predefined_product_id');
    }
}
