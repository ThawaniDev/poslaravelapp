<?php

namespace App\Domain\StaffManagement\Enums;

enum ShiftScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Missed = 'missed';
    case Swapped = 'swapped';
}
