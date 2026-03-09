<?php

namespace App\Domain\StaffManagement\Enums;

enum CommissionAppliesTo: string
{
    case AllSales = 'all_sales';
    case SpecificCategory = 'specific_category';
    case SpecificProduct = 'specific_product';
}
