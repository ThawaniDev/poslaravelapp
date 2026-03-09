<?php

namespace App\Domain\Security\Enums;

enum LoginAttemptType: string
{
    case Pin = 'pin';
    case Password = 'password';
    case Biometric = 'biometric';
    case TwoFactor = 'two_factor';
}
