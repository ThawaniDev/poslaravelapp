<?php

namespace App\Domain\Order\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\Models\OrderStatusHistory;
use App\Domain\Shared\Traits\ScopesStoreQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    use ScopesStoreQuery;

    public function list(string|array $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->scopeByStore(Order::query(), $storeId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['search'])) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $filters['search']);
            $query->where('order_number', 'like', "%{$escaped}%");
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function find(string $orderId, ?string $storeId = null): Order
    {
        $query = Order::with(['orderItems', 'orderStatusHistory', 'returns']);
        if ($storeId) {
            $query->where('store_id', $storeId);
        }
        return $query->findOrFail($orderId);
    }

    public function create(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor) {
            $order = Order::create([
                'store_id' => $actor->store_id,
                'transaction_id' => $data['transaction_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'order_number' => $data['order_number'] ?? $this->generateOrderNumber($actor->store_id),
                'source' => $data['source'] ?? OrderSource::Pos->value,
                'status' => OrderStatus::New->value,
                'subtotal' => $data['subtotal'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'total' => $data['total'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'external_order_id' => $data['external_order_id'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Record initial status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::New->value,
                'changed_by' => $actor->id,
                'notes' => 'Order created',
            ]);

            // Create order items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemPayload = [
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'] ?? null,
                        'variant_id' => $item['variant_id'] ?? null,
                        'product_name' => $item['product_name'],
                        'product_name_ar' => $item['product_name_ar'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'total' => $item['total'],
                    ];
                    if ($itemPayload['product_id'] === null) {
                        unset($itemPayload['product_id']);
                    }
                    OrderItem::create($itemPayload);
                }
            }

            return $order->load('orderItems');
        });
    }

    public function updateStatus(Order $order, string $newStatus, User $actor, ?string $notes = null): Order
    {
        $oldStatus = $order->status;
        $newStatusEnum = OrderStatus::from($newStatus);

        // Validate status transition
        $allowed = $this->getAllowedTransitions($oldStatus);
        if (!in_array($newStatusEnum, $allowed)) {
            throw new \RuntimeException(__('orders.invalid_transition', ['from' => $oldStatus->value, 'to' => $newStatus]));
        }

        $order->update(['status' => $newStatusEnum]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $oldStatus->value,
            'to_status' => $newStatusEnum->value,
            'changed_by' => $actor->id,
            'notes' => $notes,
        ]);

        return $order->fresh();
    }

    public function void(Order $order, User $actor, ?string $notes = null): Order
    {
        $nonVoidable = [OrderStatus::Completed, OrderStatus::Delivered, OrderStatus::Voided, OrderStatus::Cancelled];
        if (in_array($order->status, $nonVoidable)) {
            throw new \RuntimeException(__('orders.cannot_void_status', ['status' => $order->status->value]));
        }

        $oldStatus = $order->status;
        $order->update(['status' => OrderStatus::Voided]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $oldStatus->value,
            'to_status' => OrderStatus::Voided->value,
            'changed_by' => $actor->id,
            'notes' => $notes ?? 'Order voided',
        ]);

        return $order->fresh();
    }

    private function getAllowedTransitions(OrderStatus $current): array
    {
        return match ($current) {
            OrderStatus::New => [OrderStatus::Preparing, OrderStatus::Cancelled, OrderStatus::Voided],
            OrderStatus::Preparing => [OrderStatus::Ready, OrderStatus::Cancelled, OrderStatus::Voided],
            OrderStatus::Ready => [OrderStatus::Dispatched, OrderStatus::PickedUp, OrderStatus::Completed, OrderStatus::Voided],
            OrderStatus::Dispatched => [OrderStatus::Delivered],
            OrderStatus::PickedUp => [OrderStatus::Completed],
            OrderStatus::Delivered => [OrderStatus::Completed],
            default => [],
        };
    }

    private function generateOrderNumber(string $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$date}-";

        // Note: Postgres disallows FOR UPDATE with aggregate functions, so we
        // select the matching rows with a row lock and count them in PHP.
        $count = DB::table('orders')
            ->select('id')
            ->where('store_id', $storeId)
            ->where('order_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->get()
            ->count();

        return sprintf('ORD-%s-%04d', $date, $count + 1);
    }
}
