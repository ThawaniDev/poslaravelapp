<?php

namespace App\Domain\Auth\Enums;

enum OtpChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
