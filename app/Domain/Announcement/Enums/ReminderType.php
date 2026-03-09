<?php

namespace App\Domain\Announcement\Enums;

enum ReminderType: string
{
    case Upcoming = 'upcoming';
    case Overdue = 'overdue';
}
