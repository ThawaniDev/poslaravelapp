<?php

namespace App\Domain\IndustryBakery\Models;

use App\Domain\Core\Models\Store;
use App\Domain\IndustryBakery\Enums\ProductionScheduleStatus;
use App\Domain\IndustryBakery\Models\BakeryRecipe;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSchedule extends Model
{
    use HasUuids;

    protected $table = 'production_schedules';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'recipe_id',
        'schedule_date',
        'planned_batches',
        'actual_batches',
        'planned_yield',
        'actual_yield',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => ProductionScheduleStatus::class,
        'schedule_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(BakeryRecipe::class, 'recipe_id');
    }
}
