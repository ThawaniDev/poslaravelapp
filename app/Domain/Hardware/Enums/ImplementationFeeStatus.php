<?php

namespace App\Domain\Hardware\Enums;

enum ImplementationFeeStatus: string
{
    case Invoiced = 'invoiced';
    case Paid = 'paid';
}
