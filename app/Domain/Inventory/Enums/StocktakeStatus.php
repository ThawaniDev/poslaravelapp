<?php

namespace App\Domain\Inventory\Enums;

enum StocktakeStatus: string
{
    case InProgress = 'in_progress';
    case Review = 'review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
