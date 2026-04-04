<?php

namespace App\Domain\StaffManagement\Enums;

enum CommissionRuleType: string
{
    case Percentage = 'percentage';
    case FlatPercentage = 'flat_percentage';
    case Tiered = 'tiered';
    case PerItem = 'per_item';
}
