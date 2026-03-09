<?php

namespace App\Domain\DeliveryPlatformRegistry\Enums;

enum DeliveryAuthMethod: string
{
    case Bearer = 'bearer';
    case ApiKey = 'api_key';
    case Basic = 'basic';
    case Oauth2 = 'oauth2';
}
