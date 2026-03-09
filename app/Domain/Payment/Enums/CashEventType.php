<?php

namespace App\Domain\Payment\Enums;

enum CashEventType: string
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
}
