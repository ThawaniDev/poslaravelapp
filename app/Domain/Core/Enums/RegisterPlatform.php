<?php

namespace App\Domain\Core\Enums;

enum RegisterPlatform: string
{
    case Windows = 'windows';
    case Macos = 'macos';
    case Ios = 'ios';
    case Android = 'android';
}
