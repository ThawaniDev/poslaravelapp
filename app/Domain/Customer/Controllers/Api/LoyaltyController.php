<?php

namespace App\Domain\Customer\Controllers\Api;

use App\Domain\Customer\Resources\LoyaltyConfigResource;
use App\Domain\Customer\Resources\LoyaltyTransactionResource;
use App\Domain\Customer\Resources\StoreCreditTransactionResource;
use App\Domain\Customer\Services\LoyaltyService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends BaseApiController
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
    ) {}

    // ─── Loyalty Config ──────────────────────────────────────

    public function config(Request $request): JsonResponse
    {
        $config = $this->loyaltyService->getConfig($request->user()->organization_id);
        if (!$config) {
            return $this->success(null, 'No loyalty config found.');
        }
        return $this->success(new LoyaltyConfigResource($config));
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'points_per_sar' => ['sometimes', 'numeric', 'min:0'],
            'sar_per_point' => ['sometimes', 'numeric', 'min:0'],
            'min_redemption_points' => ['sometimes', 'integer', 'min:1'],
            'points_expiry_months' => ['sometimes', 'integer', 'min:1'],
            'excluded_category_ids' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $config = $this->loyaltyService->saveConfig($data, $request->user());
        return $this->success(new LoyaltyConfigResource($config));
    }

    // ─── Loyalty Transactions ────────────────────────────────

    public function loyaltyLog(string $customer): JsonResponse
    {
        $paginator = $this->loyaltyService->getLoyaltyLog($customer);
        $result = $paginator->toArray();
        $result['data'] = LoyaltyTransactionResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function adjustPoints(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:earn,redeem,adjust'],
            'notes' => ['nullable', 'string'],
            'order_id' => ['nullable', 'uuid'],
        ]);

        try {
            if ($data['type'] === 'redeem') {
                $txn = $this->loyaltyService->redeemPoints(
                    $customer,
                    abs($data['points']),
                    $request->user(),
                    $data['order_id'] ?? null,
                );
            } else {
                $points = $data['type'] === 'earn' ? abs($data['points']) : $data['points'];
                $txn = $this->loyaltyService->adjustPoints(
                    $customer,
                    $points,
                    $data['type'],
                    $request->user(),
                    $data['notes'] ?? null,
                    $data['order_id'] ?? null,
                );
            }
            return $this->created(new LoyaltyTransactionResource($txn));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Store Credit ────────────────────────────────────────

    public function storeCreditLog(string $customer): JsonResponse
    {
        $paginator = $this->loyaltyService->getStoreCreditLog($customer);
        $result = $paginator->toArray();
        $result['data'] = StoreCreditTransactionResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function topUpCredit(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $txn = $this->loyaltyService->topUpCredit(
            $customer,
            $data['amount'],
            $request->user(),
            $data['notes'] ?? null,
        );

        return $this->created(new StoreCreditTransactionResource($txn));
    }

    public function adjustCredit(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
        try {
            $txn = $this->loyaltyService->adjustCredit(
                $customer,
                (float) $data['amount'],
                $request->user(),
                $data['notes'] ?? null,
            );
            return $this->created(new StoreCreditTransactionResource($txn));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Dedicated redemption endpoint (separate from generic adjust).
     */
    public function redeemPoints(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'order_id' => ['nullable', 'uuid'],
        ]);
        try {
            $txn = $this->loyaltyService->redeemPoints(
                $customer,
                (int) $data['points'],
                $request->user(),
                $data['order_id'] ?? null,
            );
            return $this->created(new LoyaltyTransactionResource($txn));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
