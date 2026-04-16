<?php

namespace App\Domain\IndustryBakery\Enums;

enum ProductionScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
