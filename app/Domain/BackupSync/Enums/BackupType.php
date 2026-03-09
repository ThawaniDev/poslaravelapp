<?php

namespace App\Domain\BackupSync\Enums;

enum BackupType: string
{
    case Auto = 'auto';
    case Manual = 'manual';
    case PreUpdate = 'pre_update';
}
