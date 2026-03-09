<?php

namespace App\Domain\ContentOnboarding\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model
{
    use HasUuids;

    protected $table = 'onboarding_steps';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'step_number',
        'title',
        'title_ar',
        'description',
        'description_ar',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

}
