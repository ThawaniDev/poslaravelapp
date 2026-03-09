<?php

namespace App\Domain\Payment\Enums;

enum PaymentMethodKey: string
{
    case Cash = 'cash';
    case CardMada = 'card_mada';
    case CardVisa = 'card_visa';
    case CardMastercard = 'card_mastercard';
    case StoreCredit = 'store_credit';
    case GiftCard = 'gift_card';
    case MobilePayment = 'mobile_payment';
}
