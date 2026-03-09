<?php

namespace App\Domain\SystemConfig\Enums;

enum TaxExemptionType: string
{
    case Diplomatic = 'diplomatic';
    case Government = 'government';
    case Export = 'export';
    case Charity = 'charity';
}
