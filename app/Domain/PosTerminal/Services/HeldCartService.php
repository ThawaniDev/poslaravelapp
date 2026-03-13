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
        return HeldCart::create([
            'store_id' => $actor->store_id,
            'register_id' => $data['register_id'] ?? null,
            'cashier_id' => $actor->id,
            'customer_id' => $data['customer_id'] ?? null,
            'cart_data' => $data['cart_data'],
            'label' => $data['label'] ?? null,
            'held_at' => now(),
        ]);
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
}
