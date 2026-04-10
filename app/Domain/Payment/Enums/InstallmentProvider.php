<?php

namespace App\Domain\Payment\Enums;

enum InstallmentProvider: string
{
    case Tabby = 'tabby';
    case Tamara = 'tamara';
    case MisPay = 'mispay';
    case Madfu = 'madfu';

    public function label(): string
    {
        return match ($this) {
            self::Tabby => 'Tabby',
            self::Tamara => 'Tamara',
            self::MisPay => 'MisPay',
            self::Madfu => 'Madfu',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Tabby => 'تابي',
            self::Tamara => 'تمارا',
            self::MisPay => 'مس باي',
            self::Madfu => 'مدفوع',
        };
    }

    public function toPaymentMethodKey(): PaymentMethodKey
    {
        return match ($this) {
            self::Tabby => PaymentMethodKey::Tabby,
            self::Tamara => PaymentMethodKey::Tamara,
            self::MisPay => PaymentMethodKey::MisPay,
            self::Madfu => PaymentMethodKey::Madfu,
        };
    }
}
