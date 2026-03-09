<?php

namespace App\Domain\IndustryFlorist\Enums;

enum FlowerSubscriptionFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
}
