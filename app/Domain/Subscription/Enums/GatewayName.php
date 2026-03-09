<?php

namespace App\Domain\Subscription\Enums;

enum GatewayName: string
{
    case ThawaniPay = 'thawani_pay';
    case Stripe = 'stripe';
    case Moyasar = 'moyasar';
}
