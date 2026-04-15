<?php

namespace App\Domain\ProviderPayment\Enums;

enum PaymentEmailType: string
{
    case PaymentConfirmation = 'payment_confirmation';
    case Invoice = 'invoice';
    case PaymentFailed = 'payment_failed';
    case RefundConfirmation = 'refund_confirmation';

    public function subject(): string
    {
        return match ($this) {
            self::PaymentConfirmation => __('provider_payments.email_subject_payment_confirmation'),
            self::Invoice => __('provider_payments.email_subject_invoice'),
            self::PaymentFailed => __('provider_payments.email_subject_payment_failed'),
            self::RefundConfirmation => __('provider_payments.email_subject_refund_confirmation'),
        };
    }
}
