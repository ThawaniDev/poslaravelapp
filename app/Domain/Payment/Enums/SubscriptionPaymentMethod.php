<?php

namespace App\Domain\Payment\Enums;

enum SubscriptionPaymentMethod: string
{
    case CreditCard = 'credit_card';
    case Mada = 'mada';
    case BankTransfer = 'bank_transfer';
}
