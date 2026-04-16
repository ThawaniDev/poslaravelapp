<?php

namespace App\Domain\Payment\Enums;

enum PaymentMethodCategory: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Digital = 'digital';
    case Electronic = 'electronic';
    case Credit = 'credit';
    case BankTransfer = 'bank_transfer';
    case Installment = 'installment';
}
