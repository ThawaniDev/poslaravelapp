<?php

namespace App\Domain\Customer\Enums;

enum DigitalReceiptStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
