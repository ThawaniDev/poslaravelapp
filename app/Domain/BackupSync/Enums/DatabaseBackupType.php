<?php

namespace App\Domain\BackupSync\Enums;

enum DatabaseBackupType: string
{
    case AutoDaily = 'auto_daily';
    case AutoWeekly = 'auto_weekly';
    case Manual = 'manual';
}
