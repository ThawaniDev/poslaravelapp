<?php

namespace App\Domain\Customer\Enums;

enum DigitalReceiptChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
