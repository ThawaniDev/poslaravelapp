<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaQrPosition: string
{
    case Header = 'header';
    case Footer = 'footer';
    case Bottom = 'bottom';
}
