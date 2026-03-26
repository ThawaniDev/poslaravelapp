<?php

namespace App\Domain\ContentOnboarding\Enums;

enum SubscriptionInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
