<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\StaffManagement\Enums\CommissionRuleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    use HasUuids;

    protected $table = 'commission_rules';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'staff_user_id',
        'type',
        'percentage',
        'tiers_json',
        'product_category_id',
        'is_active',
    ];

    protected $casts = [
        'type' => CommissionRuleType::class,
        'tiers_json' => 'array',
        'is_active' => 'boolean',
        'percentage' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
    public function commissionEarnings(): HasMany
    {
        return $this->hasMany(CommissionEarning::class);
    }
}
