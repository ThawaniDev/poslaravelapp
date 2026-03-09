<?php

namespace App\Domain\ContentOnboarding\Enums;

enum BusinessPromotionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case Bogo = 'bogo';
    case HappyHour = 'happy_hour';
}
