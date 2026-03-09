<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaInvoiceType: string
{
    case Standard = 'standard';
    case Simplified = 'simplified';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';
}
