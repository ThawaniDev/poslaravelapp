<?php

namespace App\Domain\Auth\Enums;

enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';
    case Windows = 'windows';
    case Macos = 'macos';
    case Linux = 'linux';
}
