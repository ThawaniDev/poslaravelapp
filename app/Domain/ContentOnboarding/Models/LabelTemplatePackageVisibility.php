<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LabelTemplatePackageVisibility extends Pivot
{
    protected $table = 'label_template_package_visibility';
    public $timestamps = false;

    protected $fillable = [
        'label_layout_template_id',
        'subscription_plan_id',
    ];

    public function labelLayoutTemplate(): BelongsTo
    {
        return $this->belongsTo(LabelLayoutTemplate::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
