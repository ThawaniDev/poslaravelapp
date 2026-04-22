<?php

namespace App\Domain\WameedAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AIFeatureInvoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?string $storeId,
        public readonly string $featureSlug,
        public readonly string $userId,
        public readonly ?string $organizationId = null,
        public readonly ?int $tokensUsed = null,
        public readonly ?float $costEstimate = null,
        public readonly ?int $processingTimeMs = null,
    ) {}
}
