<?php

namespace App\Domain\WameedAI\Providers;

use App\Domain\WameedAI\Events\AIFeatureInvoked;
use App\Domain\WameedAI\Events\AISuggestionAccepted;
use App\Domain\WameedAI\Events\AISuggestionDismissed;
use App\Domain\WameedAI\Listeners\LogAIFeatureUsage;
use App\Domain\WameedAI\Listeners\ProcessAcceptedSuggestion;
use App\Domain\WameedAI\Listeners\ProcessDismissedSuggestion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WameedAIServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(AIFeatureInvoked::class, LogAIFeatureUsage::class);
        Event::listen(AISuggestionAccepted::class, ProcessAcceptedSuggestion::class);
        Event::listen(AISuggestionDismissed::class, ProcessDismissedSuggestion::class);
    }
}
