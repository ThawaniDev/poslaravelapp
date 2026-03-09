<?php

namespace App\Domain\Notification\Enums;

enum FcmDeviceType: string
{
    case Android = 'android';
    case Ios = 'ios';
}
