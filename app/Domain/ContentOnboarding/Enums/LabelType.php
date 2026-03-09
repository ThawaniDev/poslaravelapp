<?php

namespace App\Domain\ContentOnboarding\Enums;

enum LabelType: string
{
    case Barcode = 'barcode';
    case Price = 'price';
    case Shelf = 'shelf';
    case Jewelry = 'jewelry';
    case Pharmacy = 'pharmacy';
}
