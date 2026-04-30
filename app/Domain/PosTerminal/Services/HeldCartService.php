<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use App\Domain\PosTerminal\Models\HeldCart;
use Illuminate\Database\Eloquent\Collection;

class HeldCartService
{
    public function list(string $storeId): Collection
    {
        return HeldCart::where('store_id', $storeId)
            ->whereNull('recalled_at')
            ->orderByDesc('held_at')
            ->get();
    }

    public function hold(array $data, User $actor): HeldCart
    {
        $payload = [
            'store_id' => $actor->store_id,
            'cashier_id' => $actor->id,
            'customer_id' => $data['customer_id'] ?? null,
            'cart_data' => $data['cart_data'],
            'label' => $data['label'] ?? null,
            'held_at' => now(),
        ];

        if (! empty($data['register_id'])) {
            $payload['register_id'] = $data['register_id'];
        }

        return HeldCart::create($payload);
    }

    public function recall(HeldCart $cart, User $actor): HeldCart
    {
        if ($cart->recalled_at) {
            throw new \RuntimeException('This cart has already been recalled.');
        }

        $cart->update([
            'recalled_at' => now(),
            'recalled_by' => $actor->id,
        ]);

        return $cart;
    }

    public function delete(HeldCart $cart): void
    {
        $cart->delete();
    }

    /**
     * Delete every held cart that has been sitting in `held_carts` longer
     * than the per-store `held_cart_expiry_hours` setting (default 24h)
     * and has not been recalled. Returns the number of rows deleted so the
     * scheduler / command can log it.
     */
    public function purgeExpired(): int
    {
        $totalDeleted = 0;
        $stores = \App\Domain\Core\Models\StoreSettings::query()
            ->select(['store_id', 'held_cart_expiry_hours'])
            ->get();

        // Delete per-store using each store's threshold so configurations that
        // intentionally keep carts longer (e.g. 72h for B2B quotes) are honoured.
        foreach ($stores as $row) {
            $hours = max(1, (int) ($row->held_cart_expiry_hours ?? 24));
            $cutoff = now()->subHours($hours);
            $totalDeleted += HeldCart::where('store_id', $row->store_id)
                ->whereNull('recalled_at')
                ->where('held_at', '<', $cutoff)
                ->delete();
        }

        // Stores with no settings row: fall back to the global 24h default.
        $cutoff24 = now()->subHours(24);
        $totalDeleted += HeldCart::whereNull('recalled_at')
            ->where('held_at', '<', $cutoff24)
            ->whereNotIn('store_id', $stores->pluck('store_id'))
            ->delete();

        return $totalDeleted;
    }
}
