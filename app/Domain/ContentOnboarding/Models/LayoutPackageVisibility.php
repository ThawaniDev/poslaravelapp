<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LayoutPackageVisibility extends Pivot
{
    protected $table = 'layout_package_visibility';
    public $timestamps = false;

    protected $fillable = [
        'pos_layout_template_id',
        'subscription_plan_id',
    ];

    public function posLayoutTemplate(): BelongsTo
    {
        return $this->belongsTo(PosLayoutTemplate::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
