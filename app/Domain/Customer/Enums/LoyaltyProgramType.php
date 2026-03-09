<?php

namespace App\Domain\Customer\Enums;

enum LoyaltyProgramType: string
{
    case Points = 'points';
    case Stamps = 'stamps';
    case Cashback = 'cashback';
    case None = 'none';
}
