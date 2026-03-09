<?php

namespace App\Domain\Shared\Enums;

enum ActivityEntityType: string
{
    case Order = 'order';
    case Product = 'product';
    case Customer = 'customer';
}
