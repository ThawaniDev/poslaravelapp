<?php

namespace App\Domain\BackupSync\Enums;

enum SyncLogStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
}
