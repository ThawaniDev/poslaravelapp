<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum WebhookEventType: string
{
    case NewOrder = 'new_order';
    case OrderUpdate = 'order_update';
    case OrderCancelled = 'order_cancelled';
    case DriverAssigned = 'driver_assigned';
    case DriverArrived = 'driver_arrived';
    case OrderPickedUp = 'order_picked_up';
    case OrderDelivered = 'order_delivered';
    case PaymentConfirmed = 'payment_confirmed';
    case RatingReceived = 'rating_received';

    public function label(): string
    {
        return match ($this) {
            self::NewOrder => __('delivery.webhook.new_order'),
            self::OrderUpdate => __('delivery.webhook.order_update'),
            self::OrderCancelled => __('delivery.webhook.order_cancelled'),
            self::DriverAssigned => __('delivery.webhook.driver_assigned'),
            self::DriverArrived => __('delivery.webhook.driver_arrived'),
            self::OrderPickedUp => __('delivery.webhook.order_picked_up'),
            self::OrderDelivered => __('delivery.webhook.order_delivered'),
            self::PaymentConfirmed => __('delivery.webhook.payment_confirmed'),
            self::RatingReceived => __('delivery.webhook.rating_received'),
        };
    }
}
