<?php

namespace App\Domain\Notification\Observers;

use App\Domain\Notification\Services\NotificationDispatcher;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderNotificationObserver
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function created(Order $order): void
    {
        try {
            $store = $order->store;
            $currency = $store?->currency ?? 'OMR';
            $vars = [
                'order_id' => $order->order_number ?? $order->id,
                'total' => number_format((float) $order->total, 2) . ' ' . $currency,
                'store_name' => $store?->name ?? '',
            ];

            $eventKey = $order->external_order_id
                ? 'order.new_external'
                : 'order.new';

            if ($order->external_order_id) {
                $vars['platform'] = $order->source?->value ?? 'external';
                $vars['customer_name'] = $order->customer?->name ?? '';
                $vars['item_count'] = (string) $order->orderItems()->count();
            }

            $this->dispatcher->toStoreOwner(
                storeId: $order->store_id,
                eventKey: $eventKey,
                variables: $vars,
                category: 'order',
                referenceId: $order->id,
                referenceType: 'order',
            );
        } catch (\Throwable $e) {
            Log::error('OrderNotificationObserver::created failed', ['error' => $e->getMessage()]);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        try {
            $store = $order->store;
            $currency = $store?->currency ?? 'OMR';
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            $vars = [
                'order_id' => $order->order_number ?? $order->id,
                'total' => number_format((float) $order->total, 2) . ' ' . $currency,
                'store_name' => $store?->name ?? '',
                'old_status' => $oldStatus instanceof OrderStatus ? $oldStatus->value : (string) $oldStatus,
                'new_status' => $newStatus instanceof OrderStatus ? $newStatus->value : (string) $newStatus,
            ];

            // Determine event key based on status transition
            $eventKey = match ($newStatus) {
                OrderStatus::Completed => 'order.completed',
                OrderStatus::Cancelled => 'order.cancelled',
                OrderStatus::Voided => 'order.cancelled',
                default => 'order.status_changed',
            };

            if ($newStatus === OrderStatus::Cancelled || $newStatus === OrderStatus::Voided) {
                $vars['reason'] = $order->notes ?? 'No reason provided';
            }

            $this->dispatcher->toStoreOwner(
                storeId: $order->store_id,
                eventKey: $eventKey,
                variables: $vars,
                category: 'order',
                referenceId: $order->id,
                referenceType: 'order',
            );
        } catch (\Throwable $e) {
            Log::error('OrderNotificationObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }
}
