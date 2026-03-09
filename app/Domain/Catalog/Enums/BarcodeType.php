<?php

namespace App\Domain\Catalog\Enums;

enum BarcodeType: string
{
    case CODE128 = 'CODE128';
    case EAN13 = 'EAN13';
    case EAN8 = 'EAN8';
    case QR = 'QR';
    case Code39 = 'Code39';
}
