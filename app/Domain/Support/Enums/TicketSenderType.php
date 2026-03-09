<?php

namespace App\Domain\Support\Enums;

enum TicketSenderType: string
{
    case Provider = 'provider';
    case Admin = 'admin';
}
