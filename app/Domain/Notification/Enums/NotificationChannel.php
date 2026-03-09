<?php

namespace App\Domain\Notification\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Push = 'push';
    case Sms = 'sms';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Sound = 'sound';
}
