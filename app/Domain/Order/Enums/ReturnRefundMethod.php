<?php

namespace App\Domain\Order\Enums;

enum ReturnRefundMethod: string
{
    case OriginalMethod = 'original_method';
    case Cash = 'cash';
    case StoreCredit = 'store_credit';
}
