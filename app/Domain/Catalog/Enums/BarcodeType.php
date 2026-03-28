<?php

namespace App\Domain\Catalog\Enums;

enum BarcodeType: string
{
    case CODE128 = 'CODE128';
    case Code128Lower = 'code128';
    case EAN13 = 'EAN13';
    case EAN13Lower = 'ean13';
    case EAN8 = 'EAN8';
    case EAN8Lower = 'ean8';
    case QR = 'QR';
    case QRLower = 'qr';
    case Code39 = 'Code39';
    case Code39Lower = 'code39';
}
