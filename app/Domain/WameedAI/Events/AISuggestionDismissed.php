<?php

namespace App\Domain\WameedAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AISuggestionDismissed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $suggestionId,
        public readonly string $storeId,
        public readonly string $featureSlug,
        public readonly string $userId,
        public readonly ?string $dismissalReason = null,
    ) {}
}
