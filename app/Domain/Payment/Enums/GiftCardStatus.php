<?php

namespace App\Domain\Payment\Enums;

enum GiftCardStatus: string
{
    case Active = 'active';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Deactivated = 'deactivated';
}
