<?php

namespace App\Domain\Order\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Order\Enums\ReturnType;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\ReturnItem;
use App\Domain\Order\Models\SaleReturn;
use App\Domain\Shared\Traits\ScopesStoreQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    use ScopesStoreQuery;

    public function listReturns(string|array $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scopeByStore(SaleReturn::query(), $storeId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(string $returnId): SaleReturn
    {
        return SaleReturn::with('returnItems')->findOrFail($returnId);
    }

    public function createReturn(Order $order, array $data, User $actor): SaleReturn
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $payload = [
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'return_number' => $this->generateReturnNumber($order->store_id),
                'type' => $data['type'] ?? ReturnType::Full->value,
                'reason_code' => $data['reason_code'] ?? null,
                'refund_method' => $data['refund_method'] ?? 'original_method',
                'subtotal' => $data['subtotal'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'total_refund' => $data['total_refund'],
                'notes' => $data['notes'] ?? null,
                'processed_by' => $actor->id,
            ];
            if ($payload['reason_code'] === null) {
                unset($payload['reason_code']);
            }
            $return = SaleReturn::create($payload);

            // Create return items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemPayload = [
                        'return_id' => $return->id,
                        'order_item_id' => $item['order_item_id'] ?? null,
                        'product_id' => $item['product_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'refund_amount' => $item['refund_amount'],
                    ];
                    if ($itemPayload['product_id'] === null) {
                        unset($itemPayload['product_id']);
                    }
                    if ($itemPayload['order_item_id'] === null) {
                        unset($itemPayload['order_item_id']);
                    }
                    ReturnItem::create($itemPayload);
                }
            }

            return $return->load('returnItems');
        });
    }

    private function generateReturnNumber(string $storeId): string
    {
        $date = now()->format('Ymd');
        $count = SaleReturn::where('store_id', $storeId)
            ->where('return_number', 'like', "RTN-{$date}-%")
            ->count();

        return sprintf('RTN-%s-%04d', $date, $count + 1);
    }
}
