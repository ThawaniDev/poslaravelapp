<?php

namespace App\Domain\Notification\Enums;

enum NotificationProvider: string
{
    case Unifonic = 'unifonic';
    case Taqnyat = 'taqnyat';
    case Msegat = 'msegat';
    case Mailgun = 'mailgun';
    case Ses = 'ses';
    case Smtp = 'smtp';
}
