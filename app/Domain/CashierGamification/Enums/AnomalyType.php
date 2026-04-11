<?php

namespace App\Domain\CashierGamification\Enums;

enum AnomalyType: string
{
    case ExcessiveVoids = 'excessive_voids';
    case ExcessiveNoSales = 'excessive_no_sales';
    case ExcessiveDiscounts = 'excessive_discounts';
    case ExcessivePriceOverrides = 'excessive_price_overrides';
    case CashVariance = 'cash_variance';
    case UnusualPattern = 'unusual_pattern';
}
