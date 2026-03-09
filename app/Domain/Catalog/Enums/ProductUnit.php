<?php

namespace App\Domain\Catalog\Enums;

enum ProductUnit: string
{
    case Piece = 'piece';
    case Kg = 'kg';
    case Litre = 'litre';
    case Custom = 'custom';
}
