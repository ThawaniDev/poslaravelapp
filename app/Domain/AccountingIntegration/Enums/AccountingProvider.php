<?php

namespace App\Domain\AccountingIntegration\Enums;

enum AccountingProvider: string
{
    case Quickbooks = 'quickbooks';
    case Xero = 'xero';
    case Qoyod = 'qoyod';
}
