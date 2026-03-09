<?php

namespace App\Domain\ContentOnboarding\Enums;

enum GamificationChallengeType: string
{
    case BuyXGetY = 'buy_x_get_y';
    case SpendTarget = 'spend_target';
    case VisitStreak = 'visit_streak';
    case CategorySpend = 'category_spend';
    case Referral = 'referral';
}
