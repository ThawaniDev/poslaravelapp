<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'current_step' => $this->current_step?->value ?? $this->current_step,
            'completed_steps' => $this->completed_steps ?? [],
            'checklist_items' => $this->checklist_items ?? [],
            'is_wizard_completed' => $this->is_wizard_completed,
            'is_checklist_dismissed' => $this->is_checklist_dismissed,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_percent' => $this->completionPercent(),
        ];
    }

    private function completionPercent(): int
    {
        $steps = \App\Domain\Core\Services\OnboardingService::STEP_ORDER;
        $completed = $this->completed_steps ?? [];
        if (empty($steps)) return 0;
        return (int) round(count($completed) / count($steps) * 100);
    }
}
