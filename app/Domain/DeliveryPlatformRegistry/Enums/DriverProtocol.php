<?php

namespace App\Domain\DeliveryPlatformRegistry\Enums;

enum DriverProtocol: string
{
    case EscPos = 'esc_pos';
    case StarPrnt = 'star_prnt';
    case Zpl = 'zpl';
    case Tspl = 'tspl';
    case SerialScale = 'serial_scale';
    case SerialCas = 'serial_cas';
    case Hid = 'hid';
    case Rj12Kick = 'rj12_kick';
    case Nexo = 'nexo';
    case NearpaySdk = 'nearpay_sdk';
    case NfcHid = 'nfc_hid';
}
