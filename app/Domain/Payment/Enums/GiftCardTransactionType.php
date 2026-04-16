<?php

namespace App\Domain\Payment\Enums;

enum GiftCardTransactionType: string
{
    case Redemption = 'redemption';
    case Redeem = 'redeem';
    case TopUp = 'top_up';
    case Refund = 'refund';
    case Activation = 'activation';
    case Void = 'void';
}
