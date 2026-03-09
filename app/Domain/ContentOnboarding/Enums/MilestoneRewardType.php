<?php

namespace App\Domain\ContentOnboarding\Enums;

enum MilestoneRewardType: string
{
    case Points = 'points';
    case DiscountPercentage = 'discount_percentage';
    case TierUpgrade = 'tier_upgrade';
    case Badge = 'badge';
}
