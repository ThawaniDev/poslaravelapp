<?php

namespace App\Domain\BackupSync\Enums;

enum BackupHistoryStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Corrupted = 'corrupted';
}
