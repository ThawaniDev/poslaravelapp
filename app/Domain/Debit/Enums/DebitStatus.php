<?php

namespace App\Domain\Debit\Enums;

enum DebitStatus: string
{
    case Pending = 'pending';
    case PartiallyAllocated = 'partially_allocated';
    case FullyAllocated = 'fully_allocated';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyAllocated => 'Partially Allocated',
            self::FullyAllocated => 'Fully Allocated',
            self::Reversed => 'Reversed',
        };
    }
}
