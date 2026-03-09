<?php

namespace App\Domain\ContentOnboarding\Enums;

enum BadgeTriggerType: string
{
    case PurchaseCount = 'purchase_count';
    case SpendTotal = 'spend_total';
    case StreakDays = 'streak_days';
    case CategoryExplorer = 'category_explorer';
    case ReferralCount = 'referral_count';
}
