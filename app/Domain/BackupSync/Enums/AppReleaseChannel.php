<?php

namespace App\Domain\BackupSync\Enums;

enum AppReleaseChannel: string
{
    case Stable = 'stable';
    case Beta = 'beta';
    case Testflight = 'testflight';
    case InternalTest = 'internal_test';
}
