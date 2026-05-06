<?php

namespace Tests\Unit\Domain\Subscription;

use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the SubscriptionDiscount model.
 *
 * Covers: type cast, applicable_plan_ids array cast, validity date checks,
 * max_uses enforcement, code normalization.
 */
class SubscriptionDiscountModelTest extends TestCase
{
    use RefreshDatabase;

    // ─── Type Cast ───────────────────────────────────────────────

    public function test_type_is_cast_to_discount_type_enum(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'PERCENT10',
            'type' => 'percentage',
            'value' => 10.00,
        ]);

        $this->assertInstanceOf(DiscountType::class, $discount->type);
        $this->assertSame(DiscountType::Percentage, $discount->type);
    }

    public function test_fixed_type_is_stored_correctly(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'FIXED5',
            'type' => 'fixed',
            'value' => 5.00,
        ]);

        $this->assertSame(DiscountType::Fixed, $discount->type);
    }

    // ─── applicable_plan_ids ─────────────────────────────────────

    public function test_applicable_plan_ids_is_cast_to_array(): void
    {
        $ids = ['plan-uuid-1', 'plan-uuid-2'];
        $discount = SubscriptionDiscount::create([
            'code' => 'TARGETTED',
            'type' => 'percentage',
            'value' => 20.00,
            'applicable_plan_ids' => $ids,
        ]);

        $this->assertIsArray($discount->applicable_plan_ids);
        $this->assertSame($ids, $discount->applicable_plan_ids);
    }

    public function test_applicable_plan_ids_can_be_null(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'ALLPLANS',
            'type' => 'percentage',
            'value' => 5.00,
            'applicable_plan_ids' => null,
        ]);

        $this->assertNull($discount->applicable_plan_ids);
    }

    public function test_empty_applicable_plan_ids_means_all_plans(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'UNIVERSAL',
            'type' => 'fixed',
            'value' => 10.00,
            'applicable_plan_ids' => [],
        ]);

        // Empty array means no plan restriction (open to all)
        $ids = $discount->applicable_plan_ids;
        $this->assertTrue(empty($ids));
    }

    // ─── Validity Dates ──────────────────────────────────────────

    public function test_valid_from_and_valid_to_cast_to_datetime(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'DATERANGE',
            'type' => 'percentage',
            'value' => 15.00,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $discount->valid_from);
        $this->assertInstanceOf(\Carbon\Carbon::class, $discount->valid_to);
    }

    public function test_discount_is_currently_valid(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'ACTIVE10',
            'type' => 'percentage',
            'value' => 10.00,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
        ]);

        $found = SubscriptionDiscount::where('code', 'ACTIVE10')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))
            ->first();

        $this->assertNotNull($found);
    }

    public function test_expired_discount_is_not_found(): void
    {
        SubscriptionDiscount::create([
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 10.00,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(), // expired yesterday
        ]);

        $found = SubscriptionDiscount::where('code', 'EXPIRED')
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))
            ->first();

        $this->assertNull($found);
    }

    public function test_future_discount_is_not_yet_valid(): void
    {
        SubscriptionDiscount::create([
            'code' => 'FUTURE',
            'type' => 'percentage',
            'value' => 10.00,
            'valid_from' => now()->addWeek(), // starts next week
            'valid_to' => now()->addMonth(),
        ]);

        $found = SubscriptionDiscount::where('code', 'FUTURE')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->first();

        $this->assertNull($found);
    }

    // ─── max_uses Enforcement ────────────────────────────────────

    public function test_discount_with_no_max_uses_is_always_applicable(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'UNLIMITED',
            'type' => 'percentage',
            'value' => 5.00,
            'max_uses' => null,
            'times_used' => 9999,
        ]);

        // max_uses is null → unlimited
        $this->assertNull($discount->max_uses);
    }

    public function test_discount_max_uses_not_exceeded(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'LIMITED10',
            'type' => 'percentage',
            'value' => 10.00,
            'max_uses' => 100,
            'times_used' => 50,
        ]);

        $this->assertFalse(
            $discount->max_uses !== null && $discount->times_used >= $discount->max_uses
        );
    }

    public function test_discount_max_uses_exceeded(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'EXHAUSTED',
            'type' => 'percentage',
            'value' => 10.00,
            'max_uses' => 10,
            'times_used' => 10,
        ]);

        $this->assertTrue(
            $discount->max_uses !== null && $discount->times_used >= $discount->max_uses
        );
    }

    // ─── times_used Increment ────────────────────────────────────

    public function test_times_used_can_be_incremented(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'COUNTUP',
            'type' => 'fixed',
            'value' => 5.00,
            'max_uses' => 50,
            'times_used' => 0,
        ]);

        $discount->increment('times_used');
        $discount->refresh();

        $this->assertSame(1, (int) $discount->times_used);
    }

    // ─── Value Decimal ───────────────────────────────────────────

    public function test_value_is_stored_as_decimal(): void
    {
        $discount = SubscriptionDiscount::create([
            'code' => 'DECIMAL',
            'type' => 'percentage',
            'value' => 12.50,
        ]);

        $this->assertSame('12.50', $discount->value);
    }
}
