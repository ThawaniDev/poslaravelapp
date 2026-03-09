<?php

namespace App\Domain\PosTerminal\Enums;

enum TransactionType: string
{
    case Sale = 'sale';
    case Return = 'return';
    case Void = 'void';
    case Exchange = 'exchange';
}
