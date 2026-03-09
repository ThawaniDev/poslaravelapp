<?php

namespace App\Domain\Customer\Enums;

enum StoreCreditTransactionType: string
{
    case RefundCredit = 'refund_credit';
    case TopUp = 'top_up';
    case Spend = 'spend';
    case Adjust = 'adjust';
}
