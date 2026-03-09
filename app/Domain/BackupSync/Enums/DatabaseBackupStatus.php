<?php

namespace App\Domain\BackupSync\Enums;

enum DatabaseBackupStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}
