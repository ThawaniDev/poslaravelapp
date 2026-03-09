<?php

namespace App\Domain\DeliveryPlatformRegistry\Enums;

enum DriverProtocol: string
{
    case EscPos = 'esc_pos';
    case Zpl = 'zpl';
    case Tspl = 'tspl';
    case SerialScale = 'serial_scale';
    case Hid = 'hid';
    case NearpaySdk = 'nearpay_sdk';
    case NfcHid = 'nfc_hid';
}
