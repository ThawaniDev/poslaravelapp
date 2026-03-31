<?php

namespace App\Domain\Inventory\Enums;

enum StocktakeType: string
{
    case Full = 'full';
    case Partial = 'partial';
    case Category = 'category';
}
