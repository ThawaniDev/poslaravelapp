<?php

namespace App\Domain\ProviderPayment\Enums;

enum ProviderPaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('provider_payments.status_pending'),
            self::Processing => __('provider_payments.status_processing'),
            self::Completed => __('provider_payments.status_completed'),
            self::Failed => __('provider_payments.status_failed'),
            self::Refunded => __('provider_payments.status_refunded'),
            self::Voided => __('provider_payments.status_voided'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
            self::Voided => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Refunded, self::Voided]);
    }
}
