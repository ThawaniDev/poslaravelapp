<?php

namespace App\Domain\ContentOnboarding\Enums;

enum ChallengeRewardType: string
{
    case Points = 'points';
    case DiscountCoupon = 'discount_coupon';
    case FreeItem = 'free_item';
    case Badge = 'badge';
}
