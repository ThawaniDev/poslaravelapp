<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTypeReturnPolicy extends Model
{
    use HasUuids;

    protected $table = 'business_type_return_policies';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_type_id',
        'return_window_days',
        'refund_methods',
        'require_receipt',
        'restocking_fee_percentage',
        'void_grace_period_minutes',
        'require_manager_approval',
        'max_return_without_approval',
        'return_reason_required',
        'partial_return_allowed',
    ];

    protected $casts = [
        'refund_methods' => 'array',
        'require_receipt' => 'boolean',
        'require_manager_approval' => 'boolean',
        'return_reason_required' => 'boolean',
        'partial_return_allowed' => 'boolean',
        'restocking_fee_percentage' => 'decimal:2',
        'max_return_without_approval' => 'decimal:2',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
