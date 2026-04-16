<?php

namespace App\Domain\BackupSync\Enums;

enum SyncDirection: string
{
    case Push = 'push';
    case Pull = 'pull';
    case Full = 'full';
    case Upload = 'upload';
    case Download = 'download';
}
