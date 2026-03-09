<?php

namespace App\Domain\AccountingIntegration\Enums;

enum ExportTriggeredBy: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
