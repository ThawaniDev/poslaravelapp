<?php

namespace App\Domain\Shared\Enums;

enum ConnectionType: string
{
    case Usb = 'usb';
    case Network = 'network';
    case Bluetooth = 'bluetooth';
    case Serial = 'serial';
}
