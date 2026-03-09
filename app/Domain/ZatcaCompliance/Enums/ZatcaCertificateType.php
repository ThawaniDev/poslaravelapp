<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaCertificateType: string
{
    case Compliance = 'compliance';
    case Production = 'production';
}
