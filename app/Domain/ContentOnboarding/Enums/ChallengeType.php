<?php

namespace App\Domain\ContentOnboarding\Enums;

enum ChallengeType: string
{
    case PurchaseCount = 'purchase_count';
    case SpendAmount = 'spend_amount';
    case CategoryExplore = 'category_explore';
    case Streak = 'streak';
}
