<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    use HasUuids;

    protected $table = 'marketplace_categories';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'name_ar', 'slug', 'icon', 'description',
        'description_ar', 'parent_id', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(TemplateMarketplaceListing::class, 'category_id');
    }
}
