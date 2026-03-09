<?php

namespace App\Domain\Subscription\Enums;

enum GatewayEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';
}
