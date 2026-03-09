<?php

namespace App\Domain\Order\Enums;

enum CancellationFeeType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
