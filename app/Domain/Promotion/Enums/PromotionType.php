<?php

namespace App\Domain\Promotion\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case Bogo = 'bogo';
    case Bundle = 'bundle';
    case HappyHour = 'happy_hour';
}
