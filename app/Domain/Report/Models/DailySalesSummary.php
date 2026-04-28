<?php

namespace App\Domain\Report\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySalesSummary extends Model
{
    use HasUuids;

    protected $table = 'daily_sales_summary';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'store_id',
        'date',
        'total_transactions',
        'total_revenue',
        'total_cost',
        'total_discount',
        'total_tax',
        'total_refunds',
        'net_revenue',
        'cash_revenue',
        'card_revenue',
        'other_revenue',
        'avg_basket_size',
        'unique_customers',
    ];

    protected $casts = [
        'total_revenue' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_refunds' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'cash_revenue' => 'decimal:2',
        'card_revenue' => 'decimal:2',
        'other_revenue' => 'decimal:2',
        'avg_basket_size' => 'decimal:2',
        'date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
