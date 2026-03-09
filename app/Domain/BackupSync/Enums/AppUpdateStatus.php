<?php

namespace App\Domain\BackupSync\Enums;

enum AppUpdateStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Downloaded = 'downloaded';
    case Installed = 'installed';
    case Failed = 'failed';
}
