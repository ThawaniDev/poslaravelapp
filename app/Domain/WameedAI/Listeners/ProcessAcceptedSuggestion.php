<?php

namespace App\Domain\WameedAI\Listeners;

use App\Domain\WameedAI\Events\AISuggestionAccepted;
use App\Domain\WameedAI\Models\AISuggestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessAcceptedSuggestion implements ShouldQueue
{
    public function handle(AISuggestionAccepted $event): void
    {
        $suggestion = AISuggestion::find($event->suggestionId);
        if (!$suggestion) {
            return;
        }

        $suggestion->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        Log::info("WameedAI: Suggestion accepted", [
            'suggestion_id' => $event->suggestionId,
            'feature' => $event->featureSlug,
            'store_id' => $event->storeId,
        ]);
    }
}
