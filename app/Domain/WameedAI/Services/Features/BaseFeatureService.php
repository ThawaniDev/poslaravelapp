<?php

namespace App\Domain\WameedAI\Services\Features;

use App\Domain\WameedAI\Services\AIGatewayService;
use App\Domain\WameedAI\Services\AIStoreDataService;
use Illuminate\Support\Facades\DB;

abstract class BaseFeatureService
{
    public function __construct(
        protected readonly AIGatewayService $gateway,
        protected readonly AIStoreDataService $storeDataService,
    ) {}

    abstract public function getFeatureSlug(): string;

    protected function callAI(string $storeId, string $organizationId, array $contextData, ?string $userId = null, int $cacheTtlMinutes = 60): ?array
    {
        // Auto-inject comprehensive store context into all feature calls
        $storeContext = $this->storeDataService->getStoreContext($storeId, $organizationId);

        // Feature-specific data takes priority over store context
        $enrichedContext = array_merge($storeContext, $contextData);

        return $this->gateway->call(
            featureSlug: $this->getFeatureSlug(),
            storeId: $storeId,
            organizationId: $organizationId,
            contextData: $enrichedContext,
            userId: $userId,
            cacheTtlMinutes: $cacheTtlMinutes,
        );
    }

    protected function getStoreCurrency(string $storeId): string
    {
        return DB::selectOne("SELECT currency FROM stores WHERE id = ?", [$storeId])?->currency ?? 'SAR';
    }
}
