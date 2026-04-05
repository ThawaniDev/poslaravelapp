<?php

namespace App\Domain\PredefinedCatalog\Models;

use App\Domain\ContentOnboarding\Models\BusinessType as BusinessTypeModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredefinedCategory extends Model
{
    use HasUuids;

    protected $table = 'predefined_categories';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_type_id',
        'parent_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessTypeModel::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PredefinedCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PredefinedCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(PredefinedProduct::class, 'predefined_category_id');
    }
}
