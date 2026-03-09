<?php

namespace App\Domain\BackupSync\Enums;

enum SyncConflictResolution: string
{
    case LocalWins = 'local_wins';
    case CloudWins = 'cloud_wins';
    case Merged = 'merged';
}
