<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaCertificateStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
