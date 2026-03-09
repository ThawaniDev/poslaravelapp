<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum DeliverySyncStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Pending = 'pending';
}
