<?php

namespace App\Domain\Promotion\Enums;

enum PromotionAppliesTo: string
{
    case AllProducts = 'all_products';
    case SpecificCategory = 'specific_category';
}
