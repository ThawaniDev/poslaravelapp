<?php

namespace App\Domain\Subscription\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
