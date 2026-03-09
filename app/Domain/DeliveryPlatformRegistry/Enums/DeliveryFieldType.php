<?php

namespace App\Domain\DeliveryPlatformRegistry\Enums;

enum DeliveryFieldType: string
{
    case Text = 'text';
    case Password = 'password';
    case Url = 'url';
}
