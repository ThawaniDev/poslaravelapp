<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Enums\StocktakeStatus;
use App\Domain\Inventory\Enums\StocktakeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stocktake extends Model
{
    use HasUuids;

    protected $table = 'stocktakes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'reference_number',
        'type',
        'status',
        'category_id',
        'notes',
        'started_by',
        'completed_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'type' => StocktakeType::class,
        'status' => StocktakeStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function stocktakeItems(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }
}
