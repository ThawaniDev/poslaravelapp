<?php

namespace App\Domain\Payment\Enums;

enum InstallmentPaymentStatus: string
{
    case Pending = 'pending';
    case CheckoutCreated = 'checkout_created';
    case Authorized = 'authorized';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Rejected = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Expired, self::Rejected]);
    }

    public function isSuccess(): bool
    {
        return $this === self::Completed;
    }
}
