<?php

namespace App\Domain\ContentOnboarding\Enums;

enum GamificationRewardType: string
{
    case Points = 'points';
    case DiscountPercentage = 'discount_percentage';
    case FreeItem = 'free_item';
    case Badge = 'badge';
}
