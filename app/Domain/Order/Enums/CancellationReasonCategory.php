<?php

namespace App\Domain\Order\Enums;

enum CancellationReasonCategory: string
{
    case Price = 'price';
    case Features = 'features';
    case Competitor = 'competitor';
    case Support = 'support';
    case Other = 'other';
}
