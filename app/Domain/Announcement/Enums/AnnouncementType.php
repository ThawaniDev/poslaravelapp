<?php

namespace App\Domain\Announcement\Enums;

enum AnnouncementType: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Maintenance = 'maintenance';
    case Update = 'update';
    case Feature = 'feature';
}
