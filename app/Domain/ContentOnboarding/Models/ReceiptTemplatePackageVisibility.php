<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ReceiptTemplatePackageVisibility extends Pivot
{
    protected $table = 'receipt_template_package_visibility';
    public $timestamps = false;

    protected $fillable = [
        'receipt_layout_template_id',
        'subscription_plan_id',
    ];

    public function receiptLayoutTemplate(): BelongsTo
    {
        return $this->belongsTo(ReceiptLayoutTemplate::class);
    }
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
