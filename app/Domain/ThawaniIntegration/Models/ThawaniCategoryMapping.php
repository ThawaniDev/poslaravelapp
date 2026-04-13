<?php

namespace App\Domain\ThawaniIntegration\Models;

use App\Domain\Catalog\Models\Category;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThawaniCategoryMapping extends Model
{
    use HasUuids;

    protected $table = 'thawani_category_mappings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'category_id',
        'thawani_category_id',
        'sync_status',
        'sync_direction',
        'sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
