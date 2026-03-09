<?php

namespace App\Domain\DeliveryPlatformRegistry\Enums;

enum HttpMethod: string
{
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
}
