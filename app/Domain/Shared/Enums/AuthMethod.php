<?php

namespace App\Domain\Shared\Enums;

enum AuthMethod: string
{
    case Pin = 'pin';
    case Nfc = 'nfc';
    case Biometric = 'biometric';
}
