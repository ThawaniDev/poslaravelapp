<?php

namespace App\Domain\PosTerminal\Enums;

enum TransactionStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
    case Pending = 'pending';
}
