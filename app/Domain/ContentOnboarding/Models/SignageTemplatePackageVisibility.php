<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SignageTemplatePackageVisibility extends Pivot
{
    protected $table = 'signage_template_package_visibility';
    public $timestamps = false;

    protected $fillable = [
        'signage_template_id',
        'subscription_plan_id',
    ];

    public function signageTemplate(): BelongsTo
    {
        return $this->belongsTo(SignageTemplate::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
