<?php

namespace App\Domain\StaffManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEarning extends Model
{
    use HasUuids;

    protected $table = 'commission_earnings';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staff_user_id',
        'order_id',
        'commission_rule_id',
        'order_total',
        'commission_amount',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }
}
