<?php

namespace App\Domain\BackupSync\Enums;

enum AppReleasePlatform: string
{
    case Windows = 'windows';
    case Macos = 'macos';
    case Ios = 'ios';
    case Android = 'android';
}
