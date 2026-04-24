<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaDeviceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Tampered = 'tampered';
    case Revoked = 'revoked';
}
