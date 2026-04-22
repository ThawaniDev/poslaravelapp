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

    /**
     * Send the feature call to the gateway.
     * Pass `null` for `$storeId` to run in organization-wide scope (the
     * comprehensive context will aggregate across every active store).
     */
    protected function callAI(?string $storeId, string $organizationId, array $contextData, ?string $userId = null, int $cacheTtlMinutes = 60): ?array
    {
        // Auto-inject comprehensive context. Use the org-aggregator when no
        // single store was provided so the model sees combined data.
        $storeContext = $storeId
            ? $this->storeDataService->getStoreContext($storeId, $organizationId)
            : $this->storeDataService->getOrganizationContext($organizationId);

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

    protected function getStoreCurrency(?string $storeId): string
    {
        if (!$storeId) {
            return 'SAR';
        }
        return DB::selectOne("SELECT currency FROM stores WHERE id = ?", [$storeId])?->currency ?? 'SAR';
    }

    /**
     * Resolve the list of stores to query for an org-scoped feature call.
     * Returns the single given store as a one-element array, or every active
     * store in the organization when `$storeId` is null.
     *
     * @return array<int, string>
     */
    protected function resolveStoreIds(?string $storeId, string $organizationId): array
    {
        if ($storeId) {
            return [$storeId];
        }
        return DB::table('stores')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }
}
