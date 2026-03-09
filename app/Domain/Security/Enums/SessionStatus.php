<?php

namespace App\Domain\Security\Enums;

enum SessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
