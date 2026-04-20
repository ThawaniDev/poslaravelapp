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
}
