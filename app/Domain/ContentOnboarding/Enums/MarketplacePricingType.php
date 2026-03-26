<?php

namespace App\Domain\ContentOnboarding\Enums;

enum MarketplacePricingType: string
{
    case Free = 'free';
    case OneTime = 'one_time';
    case Subscription = 'subscription';
}
