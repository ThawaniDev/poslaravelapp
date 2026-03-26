<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum DeliveryOrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('delivery.statuses.pending'),
            self::Accepted => __('delivery.statuses.accepted'),
            self::Preparing => __('delivery.statuses.preparing'),
            self::Ready => __('delivery.statuses.ready'),
            self::Dispatched => __('delivery.statuses.dispatched'),
            self::Delivered => __('delivery.statuses.delivered'),
            self::Rejected => __('delivery.statuses.rejected'),
            self::Cancelled => __('delivery.statuses.cancelled'),
            self::Failed => __('delivery.statuses.failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'info',
            self::Preparing => 'primary',
            self::Ready => 'success',
            self::Dispatched => 'info',
            self::Delivered => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Rejected, self::Cancelled, self::Failed]);
    }

    /** Valid next statuses from this status */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Rejected],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Dispatched, self::Cancelled],
            self::Dispatched => [self::Delivered, self::Failed],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions());
    }
}
