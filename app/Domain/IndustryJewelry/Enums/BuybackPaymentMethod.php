<?php

namespace App\Domain\IndustryJewelry\Enums;

enum BuybackPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case CreditNote = 'credit_note';
}
