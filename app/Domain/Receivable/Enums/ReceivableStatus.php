<?php

namespace App\Domain\Receivable\Enums;

enum ReceivableStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case FullyPaid = 'fully_paid';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyPaid => 'Partially Paid',
            self::FullyPaid => 'Fully Paid',
            self::Reversed => 'Reversed',
        };
    }
}
