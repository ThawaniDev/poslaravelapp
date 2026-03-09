<?php

namespace App\Domain\ProviderRegistration\Models;

use App\Domain\ContentOnboarding\Enums\OnboardingStep;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProgress extends Model
{
    use HasUuids;

    protected $table = 'onboarding_progress';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'current_step',
        'completed_steps',
        'checklist_items',
        'is_wizard_completed',
        'is_checklist_dismissed',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'current_step' => OnboardingStep::class,
        'completed_steps' => 'array',
        'checklist_items' => 'array',
        'is_wizard_completed' => 'boolean',
        'is_checklist_dismissed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
