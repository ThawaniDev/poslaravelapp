<?php

namespace App\Domain\CashierGamification\Models;

use App\Domain\CashierGamification\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierShiftReport extends Model
{
    use HasUuids;

    protected $table = 'cashier_shift_reports';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'pos_session_id',
        'cashier_id',
        'report_date',
        'shift_start',
        'shift_end',
        'total_transactions',
        'total_revenue',
        'total_items',
        'items_per_minute',
        'avg_basket_size',
        'void_count',
        'void_amount',
        'return_count',
        'return_amount',
        'discount_count',
        'discount_amount',
        'no_sale_count',
        'price_override_count',
        'cash_variance',
        'upsell_count',
        'upsell_rate',
        'risk_score',
        'risk_level',
        'anomaly_count',
        'badges_earned',
        'summary_en',
        'summary_ar',
        'sent_to_owner',
        'sent_at',
    ];

    protected $casts = [
        'risk_level' => RiskLevel::class,
        'report_date' => 'date',
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'total_transactions' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_items' => 'integer',
        'items_per_minute' => 'decimal:2',
        'avg_basket_size' => 'decimal:2',
        'void_count' => 'integer',
        'void_amount' => 'decimal:2',
        'return_count' => 'integer',
        'return_amount' => 'decimal:2',
        'discount_count' => 'integer',
        'discount_amount' => 'decimal:2',
        'no_sale_count' => 'integer',
        'price_override_count' => 'integer',
        'cash_variance' => 'decimal:2',
        'upsell_count' => 'integer',
        'upsell_rate' => 'decimal:4',
        'risk_score' => 'decimal:2',
        'anomaly_count' => 'integer',
        'badges_earned' => 'array',
        'sent_to_owner' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Core\Models\Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'cashier_id');
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\PosTerminal\Models\PosSession::class);
    }
}
