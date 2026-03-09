<?php

namespace App\Domain\Security\Enums;

enum SecurityAlertStatus: string
{
    case New = 'new';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
}
