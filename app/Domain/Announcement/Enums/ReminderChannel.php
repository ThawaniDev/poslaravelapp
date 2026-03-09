<?php

namespace App\Domain\Announcement\Enums;

enum ReminderChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';
}
