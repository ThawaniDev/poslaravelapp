<?php

namespace App\Domain\Customer\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Customer\Enums\LoyaltyTransactionType;
use App\Domain\Customer\Enums\StoreCreditTransactionType;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\LoyaltyConfig;
use App\Domain\Customer\Models\LoyaltyTransaction;
use App\Domain\Customer\Models\StoreCreditTransaction;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function getConfig(string $orgId): ?LoyaltyConfig
    {
        return LoyaltyConfig::where('organization_id', $orgId)->first();
    }

    public function saveConfig(array $data, User $actor): LoyaltyConfig
    {
        return LoyaltyConfig::updateOrCreate(
            ['organization_id' => $actor->organization_id],
            $data,
        );
    }

    public function getLoyaltyLog(string $customerId, int $perPage = 20)
    {
        return LoyaltyTransaction::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function adjustPoints(string $customerId, int $points, string $type, User $actor, ?string $notes = null, ?string $orderId = null): LoyaltyTransaction
    {
        return DB::transaction(function () use ($customerId, $points, $type, $actor, $notes, $orderId) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);

            $newBalance = $customer->loyalty_points + $points;
            if ($newBalance < 0) {
                throw new \RuntimeException(__('loyalty.insufficient_points'));
            }

            $txn = LoyaltyTransaction::create([
                'customer_id' => $customerId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $newBalance,
                'order_id' => $orderId,
                'notes' => $notes,
                'performed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $customer->update(['loyalty_points' => $newBalance]);

            return $txn;
        });
    }

    public function redeemPoints(string $customerId, int $points, User $actor, ?string $orderId = null): LoyaltyTransaction
    {
        $customer = Customer::findOrFail($customerId);
        $config = LoyaltyConfig::where('organization_id', $customer->organization_id)->first();

        if ($config && $points < $config->min_redemption_points) {
            throw new \RuntimeException(__('loyalty.min_redemption', ['points' => $config->min_redemption_points]));
        }

        return $this->adjustPoints($customerId, -$points, LoyaltyTransactionType::Redeem->value, $actor, 'Points redeemed', $orderId);
    }

    // ─── Store Credit ──────────────────────────────────────

    public function getStoreCreditLog(string $customerId, int $perPage = 20)
    {
        return StoreCreditTransaction::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function topUpCredit(string $customerId, float $amount, User $actor, ?string $notes = null): StoreCreditTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $actor, $notes) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);

            $newBalance = (float) $customer->store_credit_balance + $amount;

            $txn = StoreCreditTransaction::create([
                'customer_id' => $customerId,
                'type' => StoreCreditTransactionType::TopUp->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'notes' => $notes,
                'performed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $customer->update(['store_credit_balance' => $newBalance]);

            return $txn;
        });
    }

    public function spendCredit(string $customerId, float $amount, User $actor, ?string $orderId = null): StoreCreditTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $actor, $orderId) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);

            $newBalance = (float) $customer->store_credit_balance - $amount;
            if ($newBalance < 0) {
                throw new \RuntimeException(__('loyalty.insufficient_credit'));
            }

            $txn = StoreCreditTransaction::create([
                'customer_id' => $customerId,
                'type' => StoreCreditTransactionType::Spend->value,
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'order_id' => $orderId,
                'notes' => 'Store credit spent',
                'performed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $customer->update(['store_credit_balance' => $newBalance]);

            return $txn;
        });
    }

    /**
     * Manual store credit adjustment (positive or negative). Used by managers.
     */
    public function adjustCredit(string $customerId, float $amount, User $actor, ?string $notes = null): StoreCreditTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $actor, $notes) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);

            $newBalance = (float) $customer->store_credit_balance + $amount;
            // Rule #5: store credit cannot go negative on manual adjust either.
            if ($newBalance < 0) {
                throw new \RuntimeException(__('loyalty.insufficient_credit'));
            }

            $txn = StoreCreditTransaction::create([
                'customer_id' => $customerId,
                'type' => StoreCreditTransactionType::Adjust->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'notes' => $notes,
                'performed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $customer->update(['store_credit_balance' => $newBalance]);
            return $txn;
        });
    }

    /**
     * Auto-credit a refund onto the customer's store credit balance.
     */
    public function refundToCredit(string $customerId, float $amount, User $actor, string $orderId): StoreCreditTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $actor, $orderId) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);
            $newBalance = (float) $customer->store_credit_balance + $amount;

            $txn = StoreCreditTransaction::create([
                'customer_id' => $customerId,
                'type' => StoreCreditTransactionType::RefundCredit->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'order_id' => $orderId,
                'notes' => 'Refund credited to store account',
                'performed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $customer->update(['store_credit_balance' => $newBalance]);
            return $txn;
        });
    }

    /**
     * Award loyalty points for a completed order (rule #2).
     * Calculates `floor($netAmount * config->points_per_sar)`.
     * Returns null when the org has no active loyalty config.
     */
    public function earnFromOrder(string $customerId, float $netAmount, User $actor, string $orderId): ?LoyaltyTransaction
    {
        $customer = Customer::find($customerId);
        if (! $customer) {
            return null;
        }
        $config = LoyaltyConfig::where('organization_id', $customer->organization_id)
            ->where('is_active', true)
            ->first();
        if (! $config || $netAmount <= 0) {
            return null;
        }

        $points = (int) floor($netAmount * (float) $config->points_per_sar);
        if ($points <= 0) {
            return null;
        }

        return $this->adjustPoints(
            $customerId,
            $points,
            LoyaltyTransactionType::Earn->value,
            $actor,
            "Earned from order #{$orderId}",
            $orderId,
        );
    }

    /**
     * Reverse points awarded by a previous earn transaction (rule #2: refunds deduct proportionally).
     */
    public function reverseEarnedPoints(string $orderId, User $actor): void
    {
        $earned = LoyaltyTransaction::where('order_id', $orderId)
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->get();
        foreach ($earned as $txn) {
            try {
                $this->adjustPoints(
                    $txn->customer_id,
                    -$txn->points,
                    LoyaltyTransactionType::VoidReversal->value,
                    $actor,
                    "Reversed for order #{$orderId}",
                    $orderId,
                );
            } catch (\RuntimeException $e) {
                // ignore (balance can't go below zero); keep going.
            }
        }
    }

    /**
     * Convert loyalty points to a SAR discount amount using the org's config.
     */
    public function pointsToCash(string $orgId, int $points): float
    {
        $config = LoyaltyConfig::where('organization_id', $orgId)->first();
        if (! $config) {
            return 0.0;
        }
        return round((float) $config->sar_per_point * $points, 2);
    }

    /**
     * Expire points older than the configured window (run by a daily cron).
     * Returns number of customers whose points were expired.
     */
    public function expireOldPoints(string $orgId, ?\DateTimeInterface $now = null): int
    {
        $config = LoyaltyConfig::where('organization_id', $orgId)->first();
        if (! $config || (int) $config->points_expiry_months <= 0) {
            return 0;
        }
        $cutoff = ($now ? \Carbon\Carbon::instance($now) : now())
            ->subMonths((int) $config->points_expiry_months);

        $expired = 0;
        $customers = Customer::where('organization_id', $orgId)
            ->where('loyalty_points', '>', 0)
            ->get();

        foreach ($customers as $customer) {
            $oldEarn = LoyaltyTransaction::where('customer_id', $customer->id)
                ->where('type', LoyaltyTransactionType::Earn->value)
                ->where('created_at', '<=', $cutoff)
                ->sum('points');
            $alreadyExpired = abs((int) LoyaltyTransaction::where('customer_id', $customer->id)
                ->where('type', LoyaltyTransactionType::Expire->value)
                ->sum('points'));
            $alreadyRedeemed = abs((int) LoyaltyTransaction::where('customer_id', $customer->id)
                ->whereIn('type', [LoyaltyTransactionType::Redeem->value, LoyaltyTransactionType::VoidReversal->value])
                ->sum('points'));
            $expirable = max(0, (int) $oldEarn - $alreadyExpired - $alreadyRedeemed);
            $expirable = min($expirable, (int) $customer->loyalty_points);
            if ($expirable > 0) {
                LoyaltyTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => LoyaltyTransactionType::Expire->value,
                    'points' => -$expirable,
                    'balance_after' => (int) $customer->loyalty_points - $expirable,
                    'notes' => 'Auto-expired',
                    'created_at' => now(),
                ]);
                $customer->update(['loyalty_points' => (int) $customer->loyalty_points - $expirable]);
                $expired++;
            }
        }
        return $expired;
    }
}
