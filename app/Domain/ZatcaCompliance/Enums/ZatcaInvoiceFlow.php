<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaInvoiceFlow: string
{
    case Clearance = 'clearance';
    case Reporting = 'reporting';
}
