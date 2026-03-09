<?php

namespace App\Domain\ContentOnboarding\Enums;

enum BusinessCommissionType: string
{
    case Percentage = 'percentage';
    case FixedPerTransaction = 'fixed_per_transaction';
    case Tiered = 'tiered';
}
