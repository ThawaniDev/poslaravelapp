<?php

namespace App\Domain\IndustryFlorist\Models;

use App\Domain\IndustryFlorist\Enums\FlowerFreshnessStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowerFreshnessLog extends Model
{
    use HasUuids;

    protected $table = 'flower_freshness_log';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'store_id',
        'received_date',
        'expected_vase_life_days',
        'markdown_date',
        'dispose_date',
        'quantity',
        'status',
    ];

    protected $casts = [
        'status' => FlowerFreshnessStatus::class,
        'received_date' => 'date',
        'markdown_date' => 'date',
        'dispose_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
