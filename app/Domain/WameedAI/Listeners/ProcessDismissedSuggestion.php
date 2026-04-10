<?php

namespace App\Domain\WameedAI\Listeners;

use App\Domain\WameedAI\Events\AISuggestionDismissed;
use App\Domain\WameedAI\Models\AISuggestion;
use App\Domain\WameedAI\Models\AIFeedback;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessDismissedSuggestion implements ShouldQueue
{
    public function handle(AISuggestionDismissed $event): void
    {
        $suggestion = AISuggestion::find($event->suggestionId);
        if (!$suggestion) {
            return;
        }

        $suggestion->update([
            'status' => 'dismissed',
            'dismissed_at' => now(),
        ]);

        if ($event->dismissalReason) {
            AIFeedback::create([
                'store_id' => $event->storeId,
                'user_id' => $event->userId,
                'rating' => 1,
                'feedback_text' => "Suggestion dismissed ({$event->featureSlug}): {$event->dismissalReason}",
                'is_helpful' => false,
            ]);
        }
    }
}
