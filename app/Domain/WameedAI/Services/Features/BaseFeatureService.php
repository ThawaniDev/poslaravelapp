<?php

namespace App\Domain\WameedAI\Services\Features;

use App\Domain\WameedAI\Services\AIGatewayService;

abstract class BaseFeatureService
{
    public function __construct(
        protected readonly AIGatewayService $gateway,
    ) {}

    abstract public function getFeatureSlug(): string;

    protected function callAI(string $storeId, string $organizationId, array $contextData, ?string $userId = null, int $cacheTtlMinutes = 60): ?array
    {
        return $this->gateway->call(
            featureSlug: $this->getFeatureSlug(),
            storeId: $storeId,
            organizationId: $organizationId,
            contextData: $contextData,
            userId: $userId,
            cacheTtlMinutes: $cacheTtlMinutes,
        );
    }
}
