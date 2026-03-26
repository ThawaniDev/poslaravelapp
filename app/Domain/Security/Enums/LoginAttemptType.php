<?php

namespace App\Domain\Security\Enums;

enum LoginAttemptType: string
{
    case Pin = 'pin';
    case Password = 'password';
    case Biometric = 'biometric';
    case TwoFactor = 'two_factor';

    public function label(): string
    {
        return match ($this) {
            self::Pin => __('security.attempt_type_pin'),
            self::Password => __('security.attempt_type_password'),
            self::Biometric => __('security.attempt_type_biometric'),
            self::TwoFactor => __('security.attempt_type_two_factor'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pin => 'primary',
            self::Password => 'info',
            self::Biometric => 'success',
            self::TwoFactor => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pin => 'heroicon-o-lock-closed',
            self::Password => 'heroicon-o-key',
            self::Biometric => 'heroicon-o-finger-print',
            self::TwoFactor => 'heroicon-o-device-phone-mobile',
        };
    }
}
