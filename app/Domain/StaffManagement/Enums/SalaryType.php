<?php

namespace App\Domain\StaffManagement\Enums;

enum SalaryType: string
{
    case Hourly = 'hourly';
    case Monthly = 'monthly';
    case CommissionOnly = 'commission_only';
}
