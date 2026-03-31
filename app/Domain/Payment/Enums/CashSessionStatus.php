<?php

namespace App\Domain\Payment\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
