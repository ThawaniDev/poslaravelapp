<?php

namespace App\Domain\CashierGamification\Enums;

enum CashierBadgeTrigger: string
{
    case SalesChampion = 'sales_champion';
    case SpeedStar = 'speed_star';
    case ConsistencyKing = 'consistency_king';
    case UpsellMaster = 'upsell_master';
    case EarlyBird = 'early_bird';
    case MarathonRunner = 'marathon_runner';
    case ZeroVoid = 'zero_void';
    case CustomerFavorite = 'customer_favorite';
}
