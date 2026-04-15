<?php

namespace App\Domain\ProviderPayment\Enums;

enum PaymentPurpose: string
{
    case Subscription = 'subscription';
    case PlanAddon = 'plan_addon';
    case AiBilling = 'ai_billing';
    case Hardware = 'hardware';
    case ImplementationFee = 'implementation_fee';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => __('provider_payments.purpose_subscription'),
            self::PlanAddon => __('provider_payments.purpose_plan_addon'),
            self::AiBilling => __('provider_payments.purpose_ai_billing'),
            self::Hardware => __('provider_payments.purpose_hardware'),
            self::ImplementationFee => __('provider_payments.purpose_implementation_fee'),
            self::Other => __('provider_payments.purpose_other'),
        };
    }
}
