<?php

namespace App\Domain\Promotion\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Promotion\Enums\PromotionType;
use App\Domain\Promotion\Models\BundleProduct;
use App\Domain\Promotion\Models\CouponCode;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionCategory;
use App\Domain\Promotion\Models\PromotionCustomerGroup;
use App\Domain\Promotion\Models\PromotionProduct;
use App\Domain\Promotion\Models\PromotionUsageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PromotionService
{
    // ─── List ────────────────────────────────────────────────

    public function list(string $orgId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Promotion::where('organization_id', $orgId)
            ->with(['couponCodes', 'promotionProducts', 'promotionCategories', 'promotionCustomerGroups', 'bundleProducts']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_coupon'])) {
            $query->where('is_coupon', filter_var($filters['is_coupon'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    // ─── Find ────────────────────────────────────────────────

    public function find(string $promotionId): Promotion
    {
        return Promotion::with([
            'couponCodes',
            'promotionProducts',
            'promotionCategories',
            'promotionCustomerGroups',
            'bundleProducts',
            'promotionUsageLog',
        ])->findOrFail($promotionId);
    }

    // ─── Create ──────────────────────────────────────────────

    public function create(array $data, User $actor): Promotion
    {
        $data['organization_id'] = $actor->organization_id;
        $data['sync_version'] = 1;
        $data['usage_count'] = 0;

        $productIds = $data['product_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        $customerGroupIds = $data['customer_group_ids'] ?? [];
        $bundleProducts = $data['bundle_products'] ?? [];

        unset($data['product_ids'], $data['category_ids'], $data['customer_group_ids'], $data['bundle_products']);

        $promotion = Promotion::create($data);

        $this->syncProducts($promotion, $productIds);
        $this->syncCategories($promotion, $categoryIds);
        $this->syncCustomerGroups($promotion, $customerGroupIds);
        $this->syncBundleProducts($promotion, $bundleProducts);

        // Auto-generate coupon code if is_coupon
        if (!empty($data['is_coupon'])) {
            $this->generateCoupons($promotion, 1);
        }

        return $promotion->fresh([
            'couponCodes', 'promotionProducts', 'promotionCategories',
            'promotionCustomerGroups', 'bundleProducts',
        ]);
    }

    // ─── Update ──────────────────────────────────────────────

    public function update(Promotion $promotion, array $data): Promotion
    {
        $productIds = $data['product_ids'] ?? null;
        $categoryIds = $data['category_ids'] ?? null;
        $customerGroupIds = $data['customer_group_ids'] ?? null;
        $bundleProducts = $data['bundle_products'] ?? null;

        unset($data['product_ids'], $data['category_ids'], $data['customer_group_ids'], $data['bundle_products']);

        $data['sync_version'] = ($promotion->sync_version ?? 0) + 1;
        $promotion->update($data);

        if ($productIds !== null) {
            $this->syncProducts($promotion, $productIds);
        }
        if ($categoryIds !== null) {
            $this->syncCategories($promotion, $categoryIds);
        }
        if ($customerGroupIds !== null) {
            $this->syncCustomerGroups($promotion, $customerGroupIds);
        }
        if ($bundleProducts !== null) {
            $this->syncBundleProducts($promotion, $bundleProducts);
        }

        return $promotion->fresh([
            'couponCodes', 'promotionProducts', 'promotionCategories',
            'promotionCustomerGroups', 'bundleProducts',
        ]);
    }

    // ─── Delete ──────────────────────────────────────────────

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    // ─── Toggle Active ───────────────────────────────────────

    public function toggleActive(Promotion $promotion): Promotion
    {
        $promotion->update([
            'is_active' => !$promotion->is_active,
            'sync_version' => ($promotion->sync_version ?? 0) + 1,
        ]);
        return $promotion->fresh();
    }

    // ─── Coupon Validation ──────────────────────────────────

    public function validateCoupon(string $orgId, string $code, ?string $customerId = null, float $orderTotal = 0): array
    {
        $coupon = CouponCode::where('code', strtoupper($code))
            ->whereHas('promotion', fn ($q) => $q->where('organization_id', $orgId))
            ->with('promotion')
            ->first();

        if (!$coupon) {
            return ['valid' => false, 'error' => 'coupon_not_found', 'message' => 'Coupon code not found.'];
        }

        if (!$coupon->is_active) {
            return ['valid' => false, 'error' => 'coupon_inactive', 'message' => 'Coupon code is inactive.'];
        }

        $promo = $coupon->promotion;

        if (!$promo->is_active) {
            return ['valid' => false, 'error' => 'promotion_inactive', 'message' => 'Promotion is inactive.'];
        }

        // Check date validity
        $now = now();
        if ($promo->valid_from && $now->lt($promo->valid_from)) {
            return ['valid' => false, 'error' => 'not_started', 'message' => 'Promotion has not started yet.'];
        }
        if ($promo->valid_to && $now->gt($promo->valid_to)) {
            return ['valid' => false, 'error' => 'expired', 'message' => 'Promotion has expired.'];
        }

        // Check coupon usage limit
        if ($coupon->max_uses > 0 && $coupon->usage_count >= $coupon->max_uses) {
            return ['valid' => false, 'error' => 'coupon_exhausted', 'message' => 'Coupon has been fully redeemed.'];
        }

        // Check promotion overall usage limit
        if ($promo->max_uses && $promo->usage_count >= $promo->max_uses) {
            return ['valid' => false, 'error' => 'promotion_exhausted', 'message' => 'Promotion has reached its usage limit.'];
        }

        // Check per-customer limit
        if ($customerId && $promo->max_uses_per_customer) {
            $customerUsage = PromotionUsageLog::where('promotion_id', $promo->id)
                ->where('customer_id', $customerId)
                ->count();
            if ($customerUsage >= $promo->max_uses_per_customer) {
                return ['valid' => false, 'error' => 'customer_limit_reached', 'message' => 'You have already used this promotion.'];
            }
        }

        // Check min order total
        if ($promo->min_order_total && $orderTotal < (float) $promo->min_order_total) {
            return [
                'valid' => false,
                'error' => 'min_order_not_met',
                'message' => "Minimum order total of {$promo->min_order_total} required.",
            ];
        }

        // Active days check
        if ($promo->active_days && !in_array(strtolower($now->format('l')), array_map('strtolower', $promo->active_days))) {
            return ['valid' => false, 'error' => 'not_active_today', 'message' => 'Promotion is not active today.'];
        }

        // Calculate discount
        $discount = $this->calculateDiscount($promo, $orderTotal);

        return [
            'valid' => true,
            'promotion_id' => $promo->id,
            'coupon_code_id' => $coupon->id,
            'promotion_name' => $promo->name,
            'type' => $promo->type->value,
            'discount_amount' => $discount,
        ];
    }

    // ─── Redeem Coupon ──────────────────────────────────────

    public function redeemCoupon(string $couponCodeId, string $orderId, ?string $customerId, float $discountAmount): PromotionUsageLog
    {
        $coupon = CouponCode::with('promotion')->findOrFail($couponCodeId);

        // Increment usage counts
        $coupon->increment('usage_count');
        $coupon->promotion->increment('usage_count');

        // Deactivate coupon if fully used
        if ($coupon->max_uses > 0 && $coupon->usage_count >= $coupon->max_uses) {
            $coupon->update(['is_active' => false]);
        }

        return PromotionUsageLog::create([
            'promotion_id' => $coupon->promotion_id,
            'coupon_code_id' => $coupon->id,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'discount_amount' => $discountAmount,
        ]);
    }

    // ─── Batch Generate Coupons ─────────────────────────────

    public function generateCoupons(Promotion $promotion, int $count = 10, ?int $maxUsesPerCoupon = 1, ?string $prefix = null): array
    {
        $coupons = [];
        $prefix = $prefix ? strtoupper($prefix) : strtoupper(Str::substr($promotion->name, 0, 4));

        for ($i = 0; $i < $count; $i++) {
            $code = $prefix . '-' . strtoupper(Str::random(6));
            $coupons[] = CouponCode::create([
                'promotion_id' => $promotion->id,
                'code' => $code,
                'max_uses' => $maxUsesPerCoupon,
                'usage_count' => 0,
                'is_active' => true,
            ]);
        }

        return $coupons;
    }

    // ─── Analytics ──────────────────────────────────────────

    public function analytics(Promotion $promotion): array
    {
        $usageCount = $promotion->usage_count;
        $totalDiscount = PromotionUsageLog::where('promotion_id', $promotion->id)
            ->sum('discount_amount');
        $uniqueCustomers = PromotionUsageLog::where('promotion_id', $promotion->id)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');
        $activeCoupons = CouponCode::where('promotion_id', $promotion->id)
            ->where('is_active', true)
            ->count();
        $totalCoupons = CouponCode::where('promotion_id', $promotion->id)->count();

        return [
            'promotion_id' => $promotion->id,
            'usage_count' => $usageCount,
            'total_discount_given' => round((float) $totalDiscount, 2),
            'unique_customers' => $uniqueCustomers,
            'active_coupons' => $activeCoupons,
            'total_coupons' => $totalCoupons,
            'max_uses' => $promotion->max_uses,
            'is_active' => $promotion->is_active,
            'valid_from' => $promotion->valid_from?->toIso8601String(),
            'valid_to' => $promotion->valid_to?->toIso8601String(),
        ];
    }

    // ─── Private Helpers ────────────────────────────────────

    private function calculateDiscount(Promotion $promo, float $orderTotal): float
    {
        return match ($promo->type) {
            PromotionType::Percentage => round($orderTotal * (float) $promo->discount_value / 100, 2),
            PromotionType::FixedAmount => min((float) $promo->discount_value, $orderTotal),
            PromotionType::Bogo, PromotionType::HappyHour => round($orderTotal * ((float) ($promo->get_discount_percent ?? 100)) / 100, 2),
            PromotionType::Bundle => max(0, $orderTotal - (float) $promo->bundle_price),
            default => 0,
        };
    }

    private function syncProducts(Promotion $promotion, array $productIds): void
    {
        PromotionProduct::where('promotion_id', $promotion->id)->delete();
        foreach ($productIds as $pid) {
            PromotionProduct::create([
                'promotion_id' => $promotion->id,
                'product_id' => $pid,
            ]);
        }
    }

    private function syncCategories(Promotion $promotion, array $categoryIds): void
    {
        PromotionCategory::where('promotion_id', $promotion->id)->delete();
        foreach ($categoryIds as $cid) {
            PromotionCategory::create([
                'promotion_id' => $promotion->id,
                'category_id' => $cid,
            ]);
        }
    }

    private function syncCustomerGroups(Promotion $promotion, array $groupIds): void
    {
        PromotionCustomerGroup::where('promotion_id', $promotion->id)->delete();
        foreach ($groupIds as $gid) {
            PromotionCustomerGroup::create([
                'promotion_id' => $promotion->id,
                'customer_group_id' => $gid,
            ]);
        }
    }

    private function syncBundleProducts(Promotion $promotion, array $items): void
    {
        BundleProduct::where('promotion_id', $promotion->id)->delete();
        foreach ($items as $item) {
            BundleProduct::create([
                'promotion_id' => $promotion->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'] ?? 1,
            ]);
        }
    }
}
