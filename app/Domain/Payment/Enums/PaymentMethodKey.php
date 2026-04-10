<?php

namespace App\Domain\Payment\Enums;

enum PaymentMethodKey: string
{
    case Cash = 'cash';
    case Card = 'card';
    case CardMada = 'card_mada';
    case CardVisa = 'card_visa';
    case CardMastercard = 'card_mastercard';
    case Mada = 'mada';
    case ApplePay = 'apple_pay';
    case StcPay = 'stc_pay';
    case StoreCredit = 'store_credit';
    case GiftCard = 'gift_card';
    case MobilePayment = 'mobile_payment';
    case LoyaltyPoints = 'loyalty_points';
    case BankTransfer = 'bank_transfer';
    case Tabby = 'tabby';
    case Tamara = 'tamara';
    case MisPay = 'mispay';
    case Madfu = 'madfu';

    public function isInstallment(): bool
    {
        return in_array($this, [self::Tabby, self::Tamara, self::MisPay, self::Madfu]);
    }
}
