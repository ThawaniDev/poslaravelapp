<?php

namespace App\Domain\Payment\Enums;

enum GiftCardTransactionType: string
{
    case Redemption = 'redemption';
    case TopUp = 'top_up';
    case Refund = 'refund';
}
