<?php

namespace App\Domain\ContentOnboarding\Enums;

enum PurchaseType: string
{
    case OneTime = 'one_time';
    case Subscription = 'subscription';
}
