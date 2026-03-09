<?php

namespace App\Domain\Payment\Enums;

enum PaymentMethodCategory: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Digital = 'digital';
    case Credit = 'credit';
}
