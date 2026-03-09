<?php

namespace App\Domain\AccountingIntegration\Enums;

enum ExportFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
