<?php

namespace App\Domain\Promotion\Controllers\Api;

use App\Domain\Promotion\Models\CouponCode;
use App\Domain\Promotion\Requests\CreatePromotionRequest;
use App\Domain\Promotion\Requests\UpdatePromotionRequest;
use App\Domain\Promotion\Resources\CouponCodeResource;
use App\Domain\Promotion\Resources\PromotionResource;
use App\Domain\Promotion\Resources\PromotionUsageLogResource;
use App\Domain\Promotion\Services\PromotionService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends BaseApiController
{
    public function __construct(
        private readonly PromotionService $promotionService,
    ) {}

    // ─── CRUD ────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->promotionService->list(
            $request->user()->organization_id,
            $request->only(['search', 'is_active', 'type', 'is_coupon']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = PromotionResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function store(CreatePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionService->create(
            $request->validated(),
            $request->user(),
        );
        return $this->created(new PromotionResource($promotion));
    }

    public function show(string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== request()->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        return $this->success(new PromotionResource($found));
    }

    public function update(UpdatePromotionRequest $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $updated = $this->promotionService->update($found, $request->validated());
        return $this->success(new PromotionResource($updated));
    }

    public function destroy(Request $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $this->promotionService->delete($found);
        return $this->success(null, 'Promotion deleted successfully.');
    }

    // ─── Toggle ──────────────────────────────────────────────

    public function toggle(Request $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $updated = $this->promotionService->toggleActive($found);
        return $this->success(new PromotionResource($updated));
    }

    // ─── Coupon Validation ──────────────────────────────────

    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code'        => ['required', 'string', 'max:30'],
            'customer_id' => ['nullable', 'uuid'],
            'order_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->promotionService->validateCoupon(
            $request->user()->organization_id,
            $request->input('code'),
            $request->input('customer_id'),
            (float) $request->input('order_total', 0),
        );

        if ($result['valid']) {
            return $this->success($result);
        }

        return $this->error($result['message'], 422, $result);
    }

    // ─── Coupon Redemption ──────────────────────────────────

    public function redeemCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code_id'  => ['required', 'uuid'],
            'order_id'        => ['required', 'uuid'],
            'customer_id'     => ['nullable', 'uuid'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $log = $this->promotionService->redeemCoupon(
            $request->input('coupon_code_id'),
            $request->input('order_id'),
            $request->input('customer_id'),
            (float) $request->input('discount_amount'),
        );

        return $this->created([
            'id' => $log->id,
            'promotion_id' => $log->promotion_id,
            'discount_amount' => (float) $log->discount_amount,
        ]);
    }

    // ─── Batch Generate Coupons ─────────────────────────────

    public function generateCoupons(Request $request, string $promotion): JsonResponse
    {
        $request->validate([
            'count'    => ['required', 'integer', 'min:1', 'max:500'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'prefix'   => ['nullable', 'string', 'max:10'],
        ]);

        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }

        $coupons = $this->promotionService->generateCoupons(
            $found,
            (int) $request->input('count'),
            (int) $request->input('max_uses', 1),
            $request->input('prefix'),
        );

        return $this->created(CouponCodeResource::collection($coupons));
    }

    // ─── Analytics ──────────────────────────────────────────

    public function analytics(Request $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }

        $stats = $this->promotionService->analytics($found);
        return $this->success($stats);
    }

    // ─── Usage Log ──────────────────────────────────────────

    public function usageLog(Request $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $paginator = $this->promotionService->listUsageLog(
            $found,
            $request->only(['date_from', 'date_to']),
            (int) $request->get('per_page', 20),
        );
        $result = $paginator->toArray();
        $result['data'] = PromotionUsageLogResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    // ─── Duplicate ──────────────────────────────────────────

    public function duplicate(Request $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $copy = $this->promotionService->duplicate($found);
        return $this->created(new PromotionResource($copy));
    }

    // ─── List Coupons for a Promotion ───────────────────────

    public function listCoupons(Request $request, string $promotion): JsonResponse
    {
        $found = $this->promotionService->find($promotion);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $paginator = $this->promotionService->listCoupons(
            $found,
            $request->only(['search', 'is_active']),
            (int) $request->get('per_page', 20),
        );
        $result = $paginator->toArray();
        $result['data'] = CouponCodeResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function deleteCoupon(Request $request, string $coupon): JsonResponse
    {
        $row = CouponCode::with('promotion')->findOrFail($coupon);
        if ($row->promotion->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Coupon not found.');
        }
        $this->promotionService->deleteCoupon($row);
        return $this->success(null, 'Coupon deleted.');
    }

    // ─── Batch Generate (flat endpoint) ─────────────────────

    public function batchGenerateCoupons(Request $request): JsonResponse
    {
        $request->validate([
            'promotion_id' => ['required', 'uuid'],
            'count'        => ['required', 'integer', 'min:1', 'max:500'],
            'max_uses'     => ['nullable', 'integer', 'min:1'],
            'prefix'       => ['nullable', 'string', 'max:10'],
        ]);
        $found = $this->promotionService->find($request->input('promotion_id'));
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Promotion not found.');
        }
        $coupons = $this->promotionService->generateCoupons(
            $found,
            (int) $request->input('count'),
            (int) $request->input('max_uses', 1),
            $request->input('prefix'),
        );
        return $this->created(CouponCodeResource::collection($coupons));
    }

    // ─── POS Delta Sync ─────────────────────────────────────

    public function posSync(Request $request): JsonResponse
    {
        $since = null;
        if ($request->filled('since')) {
            try {
                $since = new \DateTimeImmutable($request->input('since'));
            } catch (\Throwable) {
                return $this->error('Invalid `since` timestamp.', 422);
            }
        }
        $data = $this->promotionService->posSync($request->user()->organization_id, $since);
        return $this->success($data);
    }

    // ─── Cart Evaluation ────────────────────────────────────

    public function evaluateCart(Request $request): JsonResponse
    {
        $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['nullable', 'uuid'],
            'items.*.category_id'   => ['nullable', 'uuid'],
            'items.*.unit_price'    => ['required', 'numeric', 'min:0'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'customer_id'           => ['nullable', 'uuid'],
            'customer_group_ids'    => ['nullable', 'array'],
            'customer_group_ids.*'  => ['uuid'],
            'coupon_code'           => ['nullable', 'string', 'max:30'],
        ]);
        $result = $this->promotionService->evaluateCart(
            $request->user()->organization_id,
            $request->only(['items', 'customer_id', 'customer_group_ids', 'coupon_code']),
        );
        return $this->success($result);
    }
}
