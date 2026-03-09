<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SignageTemplateBusinessType extends Pivot
{
    protected $table = 'signage_template_business_types';
    public $timestamps = false;

    protected $fillable = [
        'signage_template_id',
        'business_type_id',
    ];

    public function signageTemplate(): BelongsTo
    {
        return $this->belongsTo(SignageTemplate::class);
    }
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
