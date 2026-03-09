<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LabelTemplateBusinessType extends Pivot
{
    protected $table = 'label_template_business_types';
    public $timestamps = false;

    protected $fillable = [
        'label_layout_template_id',
        'business_type_id',
    ];

    public function labelLayoutTemplate(): BelongsTo
    {
        return $this->belongsTo(LabelLayoutTemplate::class);
    }
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
