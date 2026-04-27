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
use App\Domain\Promotion\Resources\PromotionResource;
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

        // Daily usage — last 30 days
        $dailyUsage = PromotionUsageLog::where('promotion_id', $promotion->id)
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw("DATE(created_at) as date, COUNT(*) as uses, SUM(discount_amount) as discount_amount")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'            => $r->date,
                'uses'            => (int) $r->uses,
                'discount_amount' => round((float) $r->discount_amount, 2),
            ])
            ->values()
            ->all();

        // Coupon vs auto split
        $couponUses = PromotionUsageLog::where('promotion_id', $promotion->id)
            ->whereNotNull('coupon_code_id')
            ->count();

        return [
            'promotion_id'         => $promotion->id,
            'usage_count'          => $usageCount,
            'total_discount_given' => round((float) $totalDiscount, 2),
            'unique_customers'     => $uniqueCustomers,
            'active_coupons'       => $activeCoupons,
            'total_coupons'        => $totalCoupons,
            'coupon_uses'          => $couponUses,
            'auto_uses'            => max(0, $usageCount - $couponUses),
            'max_uses'             => $promotion->max_uses,
            'is_active'            => $promotion->is_active,
            'valid_from'           => $promotion->valid_from?->toIso8601String(),
            'valid_to'             => $promotion->valid_to?->toIso8601String(),
            'daily_usage'          => $dailyUsage,
        ];
    }

    // ─── Usage Log ──────────────────────────────────────────

    public function listUsageLog(Promotion $promotion, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = PromotionUsageLog::where('promotion_id', $promotion->id)
            ->orderByDesc('created_at');

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query->paginate($perPage);
    }

    // ─── Duplicate ──────────────────────────────────────────

    public function duplicate(Promotion $source): Promotion
    {
        $data = $source->only([
            'organization_id', 'description', 'type', 'discount_value',
            'buy_quantity', 'get_quantity', 'get_discount_percent', 'bundle_price',
            'min_order_total', 'min_item_quantity', 'valid_from', 'valid_to',
            'active_days', 'active_time_from', 'active_time_to',
            'max_uses', 'max_uses_per_customer', 'is_stackable',
        ]);
        $data['name'] = $source->name . ' (Copy)';
        $data['is_active'] = false;
        $data['is_coupon'] = false;
        $data['usage_count'] = 0;
        $data['sync_version'] = 1;

        $new = Promotion::create($data);

        $productIds = PromotionProduct::where('promotion_id', $source->id)->pluck('product_id')->all();
        $this->syncProducts($new, $productIds);
        $categoryIds = PromotionCategory::where('promotion_id', $source->id)->pluck('category_id')->all();
        $this->syncCategories($new, $categoryIds);
        $groupIds = PromotionCustomerGroup::where('promotion_id', $source->id)->pluck('customer_group_id')->all();
        $this->syncCustomerGroups($new, $groupIds);
        $bundles = BundleProduct::where('promotion_id', $source->id)->get()
            ->map(fn ($bp) => ['product_id' => $bp->product_id, 'quantity' => $bp->quantity])->all();
        $this->syncBundleProducts($new, $bundles);

        return $new->fresh([
            'couponCodes', 'promotionProducts', 'promotionCategories',
            'promotionCustomerGroups', 'bundleProducts',
        ]);
    }

    // ─── Coupon Management ──────────────────────────────────

    public function listCoupons(Promotion $promotion, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CouponCode::where('promotion_id', $promotion->id);
        if (!empty($filters['search'])) {
            $query->where('code', 'like', '%' . strtoupper($filters['search']) . '%');
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function deleteCoupon(CouponCode $coupon): void
    {
        $coupon->delete();
    }

    // ─── POS Delta Sync ─────────────────────────────────────

    /**
     * Returns promotions that changed since the given timestamp. Also includes
     * every coupon code so the terminal can validate offline.
     */
    public function posSync(string $orgId, ?\DateTimeInterface $since): array
    {
        $query = Promotion::where('organization_id', $orgId)
            ->with(['couponCodes', 'promotionProducts', 'promotionCategories',
                'promotionCustomerGroups', 'bundleProducts']);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }
        $rows = $query->orderBy('updated_at')->get();

        return [
            'server_time' => now()->toIso8601String(),
            'promotions'  => PromotionResource::collection($rows)->resolve(),
        ];
    }

    // ─── Cart Evaluation (Online Engine) ────────────────────

    /**
     * Evaluate a cart against active promotions and return the applicable
     * discounts. The cart payload is:
     *   [
     *     'items' => [ ['product_id' => uuid, 'category_id' => uuid|null,
     *                   'unit_price' => float, 'quantity' => int] ],
     *     'customer_id' => uuid|null,
     *     'customer_group_ids' => uuid[],
     *     'coupon_code' => string|null,
     *   ]
     *
     * Returns:
     *   [ 'applied' => [ ... ], 'total_discount' => float ]
     */
    public function evaluateCart(string $orgId, array $cart): array
    {
        $items = $cart['items'] ?? [];
        $customerId = $cart['customer_id'] ?? null;
        $groupIds = array_map('strval', $cart['customer_group_ids'] ?? []);
        $couponCode = $cart['coupon_code'] ?? null;
        $now = now();

        $subtotal = 0.0;
        foreach ($items as $it) {
            $subtotal += (float) ($it['unit_price'] ?? 0) * (int) ($it['quantity'] ?? 0);
        }

        $applicablePromotions = Promotion::where('organization_id', $orgId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_coupon', false);
            })
            ->with(['promotionProducts', 'promotionCategories', 'promotionCustomerGroups', 'bundleProducts'])
            ->get()
            ->filter(fn ($p) => $this->isEligible($p, $items, $subtotal, $customerId, $groupIds, $now));

        $applied = [];
        $totalDiscount = 0.0;

        foreach ($applicablePromotions as $promo) {
            $detail = $this->applyToCart($promo, $items, $subtotal);
            if (($detail['discount'] ?? 0) > 0) {
                $applied[] = $detail;
                $totalDiscount += (float) $detail['discount'];
                if (!$promo->is_stackable) {
                    break; // non-stackable: single best promo only
                }
            }
        }

        // Coupon code — if provided, layers on top (respecting stackable flag)
        if ($couponCode) {
            $coupon = CouponCode::where('code', strtoupper($couponCode))
                ->whereHas('promotion', fn ($q) => $q->where('organization_id', $orgId))
                ->with('promotion')
                ->first();
            if ($coupon && $coupon->is_active && $coupon->promotion && $coupon->promotion->is_active) {
                $promo = $coupon->promotion;
                if ($this->isEligible($promo, $items, $subtotal, $customerId, $groupIds, $now)) {
                    $detail = $this->applyToCart($promo, $items, $subtotal);
                    if (($detail['discount'] ?? 0) > 0) {
                        $detail['coupon_code_id'] = $coupon->id;
                        $detail['coupon_code'] = $coupon->code;
                        $applied[] = $detail;
                        $totalDiscount += (float) $detail['discount'];
                    }
                }
            }
        }

        // Stacking cap: cannot exceed subtotal
        $totalDiscount = min($totalDiscount, $subtotal);

        return [
            'subtotal'        => round($subtotal, 2),
            'total_discount'  => round($totalDiscount, 2),
            'total_after'     => round(max(0, $subtotal - $totalDiscount), 2),
            'applied'         => $applied,
        ];
    }

    private function isEligible(Promotion $p, array $items, float $subtotal, ?string $customerId, array $groupIds, \DateTimeInterface $now): bool
    {
        if ($p->valid_from && $now < $p->valid_from) return false;
        if ($p->valid_to && $now > $p->valid_to) return false;
        if ($p->max_uses && $p->usage_count >= $p->max_uses) return false;
        if ($p->min_order_total && $subtotal < (float) $p->min_order_total) return false;
        $totalQty = array_sum(array_map(fn ($it) => (int) ($it['quantity'] ?? 0), $items));
        if ($p->min_item_quantity && $totalQty < $p->min_item_quantity) return false;

        // Active days
        $days = $p->active_days ?? [];
        if (!empty($days)) {
            $today = strtolower($now->format('l'));
            if (!in_array($today, array_map('strtolower', $days), true)) return false;
        }

        // Active time window
        if ($p->active_time_from && $p->active_time_to) {
            $nowT = $now->format('H:i');
            if (!($nowT >= $p->active_time_from && $nowT <= $p->active_time_to)) {
                return false;
            }
        }

        // Customer group restriction
        $requiredGroups = $p->promotionCustomerGroups->pluck('customer_group_id')->all();
        if (!empty($requiredGroups)) {
            if (empty(array_intersect($requiredGroups, $groupIds))) return false;
        }

        // Per-customer limit
        if ($customerId && $p->max_uses_per_customer) {
            $used = PromotionUsageLog::where('promotion_id', $p->id)
                ->where('customer_id', $customerId)
                ->count();
            if ($used >= $p->max_uses_per_customer) return false;
        }

        // Product / category restriction: at least one qualifying item must be in cart
        $productIds = $p->promotionProducts->pluck('product_id')->all();
        $catIds = $p->promotionCategories->pluck('category_id')->all();
        if (!empty($productIds) || !empty($catIds)) {
            $hasMatch = false;
            foreach ($items as $it) {
                $pid = $it['product_id'] ?? null;
                $cid = $it['category_id'] ?? null;
                if ($pid && in_array($pid, $productIds, true)) { $hasMatch = true; break; }
                if ($cid && in_array($cid, $catIds, true))    { $hasMatch = true; break; }
            }
            if (!$hasMatch) return false;
        }

        return true;
    }

    /**
     * Applies the promotion's discount formula to the cart and returns the
     * detail envelope that the POS displays on the receipt.
     */
    private function applyToCart(Promotion $promo, array $items, float $subtotal): array
    {
        $discount = 0.0;
        $productIds = $promo->promotionProducts->pluck('product_id')->all();
        $catIds = $promo->promotionCategories->pluck('category_id')->all();

        // Qualifying items
        $qualifying = [];
        foreach ($items as $it) {
            $pid = $it['product_id'] ?? null;
            $cid = $it['category_id'] ?? null;
            $matchesProduct = !empty($productIds) && $pid && in_array($pid, $productIds, true);
            $matchesCategory = !empty($catIds) && $cid && in_array($cid, $catIds, true);
            $matchesAll = empty($productIds) && empty($catIds);
            if ($matchesProduct || $matchesCategory || $matchesAll) {
                $qualifying[] = $it;
            }
        }
        $qualifyingTotal = array_sum(array_map(
            fn ($it) => (float) ($it['unit_price'] ?? 0) * (int) ($it['quantity'] ?? 0),
            $qualifying
        ));

        switch ($promo->type) {
            case PromotionType::Percentage:
                $discount = round($qualifyingTotal * (float) $promo->discount_value / 100, 2);
                break;

            case PromotionType::FixedAmount:
                $discount = min((float) $promo->discount_value, $qualifyingTotal);
                break;

            case PromotionType::HappyHour:
                $discount = round($qualifyingTotal * (float) ($promo->discount_value ?? 0) / 100, 2);
                break;

            case PromotionType::Bogo:
                $buy = (int) ($promo->buy_quantity ?? 0);
                $get = (int) ($promo->get_quantity ?? 0);
                $getPct = (float) ($promo->get_discount_percent ?? 100);
                if ($buy > 0 && $get > 0 && !empty($qualifying)) {
                    // Expand qualifying items into unit-priced line items sorted ascending
                    // so the cheapest receive the "get" discount (per business rule #6).
                    $units = [];
                    foreach ($qualifying as $it) {
                        $qty = (int) ($it['quantity'] ?? 0);
                        $p = (float) ($it['unit_price'] ?? 0);
                        for ($i = 0; $i < $qty; $i++) $units[] = $p;
                    }
                    sort($units);
                    $group = $buy + $get;
                    $groupCount = intdiv(count($units), $group);
                    $discountUnits = 0;
                    for ($g = 0; $g < $groupCount; $g++) {
                        for ($j = 0; $j < $get; $j++) {
                            $discount += $units[$g * $group + $j] * ($getPct / 100);
                            $discountUnits++;
                        }
                    }
                    $discount = round($discount, 2);
                }
                break;

            case PromotionType::Bundle:
                $bundleRows = $promo->bundleProducts;
                if ($bundleRows->isNotEmpty() && (float) $promo->bundle_price > 0) {
                    // How many complete bundles fit in the cart?
                    $bundlesPossible = PHP_INT_MAX;
                    $qtyByProduct = [];
                    foreach ($items as $it) {
                        $pid = $it['product_id'] ?? null;
                        if ($pid) $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0) + (int) ($it['quantity'] ?? 0);
                    }
                    $priceByProduct = [];
                    foreach ($items as $it) {
                        $pid = $it['product_id'] ?? null;
                        if ($pid && !isset($priceByProduct[$pid])) {
                            $priceByProduct[$pid] = (float) ($it['unit_price'] ?? 0);
                        }
                    }
                    foreach ($bundleRows as $bp) {
                        $need = max(1, (int) $bp->quantity);
                        $have = $qtyByProduct[$bp->product_id] ?? 0;
                        $bundlesPossible = min($bundlesPossible, intdiv($have, $need));
                    }
                    if ($bundlesPossible > 0 && $bundlesPossible !== PHP_INT_MAX) {
                        $regularPricePerBundle = 0.0;
                        foreach ($bundleRows as $bp) {
                            $regularPricePerBundle += ($priceByProduct[$bp->product_id] ?? 0) * max(1, (int) $bp->quantity);
                        }
                        $saving = $regularPricePerBundle - (float) $promo->bundle_price;
                        if ($saving > 0) {
                            $discount = round($saving * $bundlesPossible, 2);
                        }
                    }
                }
                break;
        }

        // Floor discount at qualifying total (cannot make item negative)
        $discount = max(0.0, min($discount, $qualifyingTotal));

        return [
            'promotion_id'   => $promo->id,
            'promotion_name' => $promo->name,
            'type'           => $promo->type->value,
            'discount'       => round($discount, 2),
            'is_stackable'   => (bool) $promo->is_stackable,
        ];
    }

    // ─── Private Helpers ────────────────────────────────────

    private function calculateDiscount(Promotion $promo, float $orderTotal): float
    {
        return match ($promo->type) {
            PromotionType::Percentage => round($orderTotal * (float) $promo->discount_value / 100, 2),
            PromotionType::FixedAmount => min((float) $promo->discount_value, $orderTotal),
            PromotionType::Bogo, PromotionType::HappyHour => round($orderTotal * ((float) ($promo->get_discount_percent ?? $promo->discount_value ?? 100)) / 100, 2),
            PromotionType::Bundle => max(0, $orderTotal - (float) $promo->bundle_price),
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
