<?php

namespace App\Domain\ContentOnboarding\Enums;

enum MilestoneType: string
{
    case TotalSpend = 'total_spend';
    case TotalVisits = 'total_visits';
    case MembershipDays = 'membership_days';
}
